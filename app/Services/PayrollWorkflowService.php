<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\ActivityLog;
use App\Models\PayHeader;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollWorkflowService
{
    public const STATE_DRAFT = 'draft';
    public const STATE_PENDING_APPROVAL = 'pending_approval';
    public const STATE_APPROVED = 'approved';
    public const STATE_REJECTED = 'rejected';
    public const STATE_POSTED = 'posted';
    public const STATE_PAID = 'paid';
    public const STATE_VOIDED = 'voided';

    protected const ALLOWED_TRANSITIONS = [
        self::STATE_DRAFT => [self::STATE_PENDING_APPROVAL, self::STATE_VOIDED],
        self::STATE_PENDING_APPROVAL => [self::STATE_APPROVED, self::STATE_REJECTED, self::STATE_DRAFT],
        self::STATE_REJECTED => [self::STATE_PENDING_APPROVAL, self::STATE_DRAFT, self::STATE_VOIDED],
        self::STATE_APPROVED => [self::STATE_POSTED, self::STATE_PAID, self::STATE_VOIDED],
        self::STATE_POSTED => [self::STATE_PAID, self::STATE_VOIDED],
        self::STATE_PAID => [self::STATE_VOIDED],
        self::STATE_VOIDED => [],
    ];

    public function canTransition(string $currentStatus, string $targetStatus): bool
    {
        if ($currentStatus === $targetStatus) {
            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        return in_array($targetStatus, $allowed, true);
    }

    public function transition(PayHeader $payHeader, string $targetStatus, array $context = []): PayHeader
    {
        $currentStatus = (string) ($payHeader->getRawOriginal('status') ?? $payHeader->status);

        if (!$this->canTransition($currentStatus, $targetStatus)) {
            throw new InvalidStatusTransitionException($currentStatus, $targetStatus);
        }

        $updateData = [
            'status' => $targetStatus,
            'lock_version' => ($payHeader->lock_version ?? 1) + 1,
        ];

        $user = $context['user'] ?? Auth::user();
        $userId = $user instanceof User ? $user->id : ($context['user_id'] ?? Auth::id());

        if ($targetStatus === self::STATE_PENDING_APPROVAL) {
            $updateData['submitted_at'] = now();
            $updateData['submitted_by'] = $userId;
        } elseif ($targetStatus === self::STATE_APPROVED) {
            $updateData['approved_at'] = now();
            $updateData['approved_by'] = $userId;
            if (isset($context['notes'])) {
                $updateData['notes'] = $context['notes'];
            }
        } elseif ($targetStatus === self::STATE_REJECTED) {
            $updateData['rejected_at'] = now();
            $updateData['rejected_by'] = $userId;
            if (isset($context['rejection_reason'])) {
                $updateData['rejection_reason'] = $context['rejection_reason'];
            }
        } elseif ($targetStatus === self::STATE_POSTED) {
            $updateData['posted_at'] = now();
        }

        if (isset($context['notes']) && !empty($context['notes'])) {
            $updateData['notes'] = $context['notes'];
        }

        $payHeader->update($updateData);

        return $payHeader;
    }

    /**
     * Reopen an approved payroll for correction by an authorized user.
     * Transactional and atomic with lockForUpdate to prevent race conditions with posting/paying.
     */
    public function reopenForCorrection(PayHeader $payHeader, string $reason, ?User $user = null): PayHeader
    {
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new \InvalidArgumentException('A valid reason is required to reopen an approved payroll for correction.');
        }

        $actor = $user ?? Auth::user();
        if (!$actor || !\App\Models\MenuRoleAccess::hasModuleAccess($actor, 'payroll_locked_edit_override')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User is not authorized to reopen approved payroll for correction.');
        }

        return DB::transaction(function () use ($payHeader, $trimmedReason, $actor) {
            /** @var PayHeader $lockedDraft */
            $lockedDraft = PayHeader::withoutGlobalScopes()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($payHeader->id);

            $isSuperAdmin = ($actor->role_id === \App\Models\Role::SUPER_ADMIN || $actor->role === 'admin');
            $recordCompanyId = $lockedDraft->company_id ?? ($lockedDraft->employee?->company_id ?? null);
            if (!$isSuperAdmin && $recordCompanyId && (int) $recordCompanyId !== (int) $actor->company_id) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized access to cross-company payroll record.');
            }

            $currentStatus = (string) ($lockedDraft->getRawOriginal('status') ?? $lockedDraft->status);
            if ($currentStatus !== self::STATE_APPROVED) {
                throw new InvalidStatusTransitionException($currentStatus, self::STATE_PENDING_APPROVAL);
            }

            $lockedDraft->update([
                'status' => self::STATE_PENDING_APPROVAL,
                'approved_at' => null,
                'approved_by' => null,
                'lock_version' => ($lockedDraft->lock_version ?? 1) + 1,
            ]);

            ActivityLog::log(
                'reopen_for_correction',
                'payroll_draft',
                "Reopened approved payroll #{$lockedDraft->id} for correction (approved -> pending_approval). Reason: {$trimmedReason}",
                PayHeader::class,
                $lockedDraft->id,
                null,
                null,
                $recordCompanyId,
                $actor->id
            );

            return $lockedDraft;
        });
    }

    /**
     * Return an unapproved draft (pending_approval, rejected, or draft) to draft status for batch changes.
     * Clears stale submission or rejection metadata and bumps lock_version once.
     */
    public function returnToDraftForBatchChange(PayHeader $payHeader, ?User $user = null): PayHeader
    {
        $currentStatus = (string) ($payHeader->getRawOriginal('status') ?? $payHeader->status);

        if (!in_array($currentStatus, [self::STATE_DRAFT, self::STATE_PENDING_APPROVAL, self::STATE_REJECTED], true)) {
            throw new InvalidStatusTransitionException($currentStatus, self::STATE_DRAFT);
        }

        $updateData = [
            'status' => self::STATE_DRAFT,
            'lock_version' => ($payHeader->lock_version ?? 1) + 1,
        ];

        if ($currentStatus === self::STATE_PENDING_APPROVAL) {
            $updateData['submitted_at'] = null;
            $updateData['submitted_by'] = null;
        } elseif ($currentStatus === self::STATE_REJECTED) {
            $updateData['rejected_at'] = null;
            $updateData['rejected_by'] = null;
            $updateData['rejection_reason'] = null;
        }

        $payHeader->update($updateData);

        return $payHeader;
    }
}

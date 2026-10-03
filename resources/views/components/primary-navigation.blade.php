@props(['variant' => 'a'])
@php
    $currentPath = '/'.ltrim(request()->path(), '/');
    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
    $query = array_merge(request()->query(), ['variant' => $variant]);
    $linkClass = $variant === 'b'
        ? 'text-stone-warm-200 hover:text-stone-warm-50 focus-visible:ring-stone-warm-50'
        : 'text-charcoal-800 hover:text-charcoal-900 focus-visible:ring-charcoal-900';
    $mobileActiveClass = 'bg-stone-warm-100 font-semibold text-charcoal-900';
@endphp
<nav aria-label="Primary navigation" class="hidden lg:block" data-motion="action" data-motion-delay="80">
    <ul class="flex items-center gap-1">
        @foreach(config('site.navigation') as $item)
            @php
                $isActive = $currentPath === $item['path']
                    || ($item['path'] === '/services' && str_starts_with($currentPath, '/services/'))
                    || ($item['path'] === '/team' && str_starts_with($currentPath, '/team/'));
                $href = url($item['path']).'?'.http_build_query($query);
            @endphp
            <li>
                <a href="{{ $href }}" @class([
                    'inline-flex min-h-[44px] items-center rounded-sm px-2 text-xs font-semibold uppercase tracking-wider focus:outline-none focus-visible:ring-2',
                    $linkClass,
                    'underline underline-offset-4' => $isActive,
                ]) @if($isActive) aria-current="page" @endif>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>

<details class="relative lg:hidden" data-mobile-navigation-disclosure data-testid="mobile-navigation-disclosure" data-motion="action" data-motion-delay="80">
    <summary class="flex min-h-[44px] cursor-pointer list-none items-center rounded-sm px-3 text-xs font-semibold uppercase tracking-wider focus:outline-none focus-visible:ring-2 {{ $linkClass }}" aria-controls="mobile-primary-navigation" aria-label="Open primary navigation" data-testid="mobile-navigation-toggle">
        Menu
    </summary>
    <nav id="mobile-primary-navigation" aria-label="Mobile primary navigation" class="absolute right-0 z-30 mt-2 max-h-[calc(100vh-6rem)] w-[min(20rem,calc(100vw-2rem))] overflow-y-auto border border-stone-warm-300 bg-stone-warm-50 p-2 shadow-xl" data-testid="mobile-primary-navigation" data-motion="group" data-motion-delay="40">
        <ul class="grid gap-1">
            @foreach(config('site.navigation') as $item)
                @php
                    $isActive = $currentPath === $item['path']
                        || ($item['path'] === '/services' && str_starts_with($currentPath, '/services/'))
                        || ($item['path'] === '/team' && str_starts_with($currentPath, '/team/'));
                    $href = url($item['path']).'?'.http_build_query($query);
                @endphp
                <li>
                    <a href="{{ $href }}" @class([
                        'flex min-h-[44px] items-center px-3 text-sm text-charcoal-900 hover:bg-stone-warm-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900' => true,
                        $mobileActiveClass => $isActive,
                    ]) @if($isActive) aria-current="page" @endif>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
            <li class="mt-2 border-t border-stone-warm-200 pt-2">
                <a href="{{ config('clinic.contact.whatsapp_url') }}" target="_blank" rel="noopener noreferrer" class="flex min-h-[44px] items-center justify-center bg-charcoal-900 px-4 py-2 text-sm font-semibold text-stone-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2" data-testid="mobile-navigation-contact">
                    Inquire via WhatsApp
                </a>
            </li>
        </ul>
    </nav>
</details>

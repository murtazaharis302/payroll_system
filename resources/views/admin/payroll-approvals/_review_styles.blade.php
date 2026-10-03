<style>
    .review-section {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .section-header {
        padding: 10px 15px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-weight: 700;
        color: #212529;
        background-color: #ffffff;
        border-bottom: 2px solid #f06115;
    }
    .section-header i {
        color: #f06115;
        margin-right: 6px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e9ecef;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        font-weight: 600;
        color: #6c757d;
    }
    .info-value {
        text-align: right;
        font-weight: 500;
    }
    .summary-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .summary-card h4 {
        margin-bottom: 10px;
        font-size: 16px;
    }
    .summary-card h2 {
        margin: 0;
        font-size: 36px;
        font-weight: 700;
    }
    .action-bar {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        position: sticky;
        bottom: 20px;
        z-index: 100;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }
    .status-badge {
        padding: 8px 16px;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-pending { background-color: #ffc107; color: #000; }
    .status-approved { background-color: #28a745; color: #fff; }
    .status-rejected { background-color: #dc3545; color: #fff; }
</style>

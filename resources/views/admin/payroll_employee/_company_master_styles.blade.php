<style>
.employee-item {
    background-color: #ffffff;
    color: #212529;
    border: 1px solid #e9ecef !important;
    border-radius: 8px !important;
    margin: 6px 8px !important;
    padding: 14px 16px !important;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
.employee-item:hover {
    background-color: #fff7ed !important;
    border-color: #f06115 !important;
}
.employee-item.active {
    background-color: #fff7ed !important;
    border-color: #f06115 !important;
    border-left: 5px solid #f06115 !important;
    box-shadow: 0 2px 6px rgba(240, 97, 21, 0.12);
}
.employee-item.active .emp-name-text {
    color: #111827 !important;
}
.employee-item.active .emp-monthly-text {
    color: #f06115 !important;
}
.employee-item.active .emp-subtitle-text {
    color: #4b5563 !important;
}
.employee-item.active .emp-code-badge {
    background-color: #f06115 !important;
    color: #ffffff !important;
    border-color: #f06115 !important;
}
</style>

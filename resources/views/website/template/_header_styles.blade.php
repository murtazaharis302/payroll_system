<style>
    .custom-input-header::placeholder,
    .datetime-wrapper::before {
        color: #000 !important;
        font-size: 13px !important;
        font-family: inherit !important;
    }

@media (max-width: 480px) {

    .datetime-wrapper {
        position: relative;
    }

    .datetime-wrapper::before {
        content: attr(data-placeholder);
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: #000;
        font-size: 13px;
        font-family: inherit;
    }

    .datetime-wrapper.has-value::before {
        content: "";
    }

    .real-datetime {
        color: transparent;
    }

    .real-datetime:focus {
        color: #000;
    }

    .datetime-wrapper.has-value .real-datetime {
        color: #000 !important;
    }

}

</style>
<!-- end -->

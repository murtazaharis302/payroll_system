<script>
    document.querySelectorAll('.datetime-wrapper input').forEach(function(el) {
        function checkValue() {
            if (el.value) {
                el.parentNode.classList.add('has-value');
            } else {
                el.parentNode.classList.remove('has-value');
            }
        }
        el.addEventListener('change', checkValue);
        checkValue();
    });
</script>

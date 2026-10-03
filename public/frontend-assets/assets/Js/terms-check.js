
   
$('document').ready(function(){
        const checkbox = document.getElementById('agreeTerms');
        const submitBtn = document.getElementById('OrderSubmitBtn');

        checkbox.addEventListener('change', function () {
            submitBtn.disabled = !this.checked;
        });
    })
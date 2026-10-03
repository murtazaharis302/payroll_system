function paymentRadio(){
  
    const radioInputs = document.querySelectorAll('.payment-radio');
    console.log(radioInputs)
    radioInputs.forEach((radio) => {
        radio.addEventListener('change', function () {
            // Remove active-payment class from all cards
            document.querySelectorAll('.payment-card').forEach(card => {
                card.classList.remove('active-payment');
                
            });
            
            // Add active-payment class to the currently selected one
            const label = document.querySelector(`label[for="${this.id}"]`);
            if (label) {
                label.classList.add('active-payment');
            }

            console.log('Selected Payment Method:', this.value);
        });

        // Trigger change event for pre-selected input on load
        if (radio.checked) {
            radio.dispatchEvent(new Event('change'));
        }
    });

}
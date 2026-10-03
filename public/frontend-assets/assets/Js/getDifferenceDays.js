
document.addEventListener('DOMContentLoaded', function () {
    const cartItems = document.querySelectorAll('.cart-item');
    

    cartItems.forEach(item => {
        const pickupInput = item.querySelector('.pickup-time');
        const dropoffInput = item.querySelector('.dropoff-time');
        const timeDiffDisplay = item.querySelector('.time-difference');
        const priceDisplay = item.querySelector('.calculated-price');
        const rowId = item.getAttribute('data-row-id');
        const price = item.getAttribute('data-price');
        const rent_type = item.getAttribute('data-rent-type');
        const pricePerDay = price;
       
        const calculateAndUpdate = () => {
            const pickupDate = new Date(pickupInput.value);
            const dropoffDate = new Date(dropoffInput.value);
            
            if (pickupInput.value && dropoffInput.value && dropoffDate > pickupDate) {
                const diffMs = dropoffDate - pickupDate;
                const diffHrs = diffMs / (1000 * 60 * 60);
                
                if(rent_type == 'day'){
                    var diffPeriod = Math.ceil(diffHrs / 24);
                }else{
                    var  diffPeriod = Math.ceil(diffHrs);
                }
                // 
                const DatepickupDate = pickupDate.toISOString().slice(0, 10).replace(/-/g, '-');
                $('.getDatepickupDate').val(DatepickupDate);
                // Ensure at least 1 day is charged
                if (diffPeriod < 1) diffPeriod = 1;
        
                // timeDiffDisplay.innerText = `Duration: ${diffPeriod} day(s)`;
        
                const totalPrice = diffPeriod * pricePerDay;
                // priceDisplay.innerText = `Price: $${totalPrice}`;
        
                updateCartPrice(rowId, totalPrice, diffPeriod);
            } else if (pickupInput.value && dropoffInput.value) {
                timeDiffDisplay.innerText = `Drop-off must be after Pickup.`;
                priceDisplay.innerText = '';
            } else {
                timeDiffDisplay.innerText = '';
                priceDisplay.innerText = '';
            }
        };
        

        pickupInput.addEventListener('change', calculateAndUpdate);
        dropoffInput.addEventListener('change', calculateAndUpdate);
    });

    function updateCartPrice(rowId, price, days) {
        var url_cart_price = window.baseUrl+'/update-cart-price';
        fetch(url_cart_price, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ rowId, price, days })
        })
        .then(res => res.json())
        .then(data => {
            
            showToast(data.message, data.status);
            // 
            console.log(data);
            $('.showNewPrice'+data.rowId).html(data.price);
            $('.showDuration'+data.rowId).html(data.qty);
            $('.calculate-subtotal').html(data.subtotal);
            $('.calculate-tax').html(data.tax);
            $('.calculate-total').html(data.total);
            // send val to inputs
            $('.get-subtotal').val(data.subtotal_input);
            $('.get-tax').val(data.tax_input);
            $('.get-total').val(data.total_input);
            $('.get-price-'+data.rowId).val(data.price_input);
            $('.get-duration-'+data.rowId).val(data.qty);
        }
        
        )
        .catch(err => console.error('Error:', err));
    }
});

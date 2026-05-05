// Cart quantity control functionality
document.addEventListener('DOMContentLoaded', function() {
  // Handle quantity decrease
  document.querySelectorAll('.quantity-minus').forEach(btn => {
    btn.addEventListener('click', function() {
      const productId = this.getAttribute('data-product-id');
      const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
      const currentValue = parseInt(input.value);
      if (currentValue > 1) {
        input.value = currentValue - 1;
        input.closest('form').submit();
      }
    });
  });

  // Handle quantity increase
  document.querySelectorAll('.quantity-plus').forEach(btn => {
    btn.addEventListener('click', function() {
      const productId = this.getAttribute('data-product-id');
      const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
      const currentValue = parseInt(input.value);
      if (currentValue < 99) {
        input.value = currentValue + 1;
        input.closest('form').submit();
      }
    });
  });

  // Handle manual quantity input change
  document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('change', function() {
      const value = parseInt(this.value);
      if (value >= 1 && value <= 99) {
        this.closest('form').submit();
      } else if (value < 1) {
        this.value = 1;
      } else {
        this.value = 99;
      }
    });
  });

  // Handle checkout form submission
  const checkoutForm = document.getElementById('checkoutForm');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Get form data
      const formData = new FormData(this);
      const data = {};
      formData.forEach((value, key) => {
        data[key] = value;
      });

      // Add clear cart action
      formData.append('clear_cart_after_checkout', '1');

      // Submit form via fetch to clear cart
      fetch('Cart.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(result => {
        if (result.trim() !== 'ok') {
          alert('Please complete your checkout information and try again.');
          return;
        }

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
        if (modal) {
          modal.hide();
        }

        // Display success alert
        setTimeout(() => {
          alert(
            '✅ Order Placed Successfully!\n\n' +
            'Thank you for your order, ' + data.full_name + '!\n\n' +
            'Order Details:\n' +
            '- Payment: ' + (data.payment_method === 'cod' ? 'Cash on Delivery' : 'Online Payment') + '\n' +
            '- Phone: ' + data.phone + '\n\n' +
            'We will contact you shortly to confirm your order.'
          );
          
          // Redirect to dashboard after alert
          window.location.href = 'Dashboard.php';
        }, 300);
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
      });
    });
  }
});

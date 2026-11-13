/**
 * AquaDepot - Cart Operations JavaScript
 */

function addToCart(productId, qty = 1) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('qty', qty);

    const baseUrl = document.body.getAttribute('data-base-url') || '';
    const cartUrl = `${baseUrl}/controllers/CartController.php?action=add`;

    fetch(cartUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            updateCartBadge(data.total_items);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Ditambahkan!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                alert(data.message);
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Perhatian',
                    text: data.message
                });
            } else {
                alert(data.message);
            }
        }
    })
    .catch(err => {
        console.error('Cart Error:', err);
    });
}

function updateCartQty(productId, qty) {
    const baseUrl = document.body.getAttribute('data-base-url') || '';
    const cartUrl = `${baseUrl}/controllers/CartController.php?action=update`;

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('qty', qty);

    fetch(cartUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        location.reload();
    })
    .catch(err => {
        console.error('Cart Update Error:', err);
        location.reload();
    });
}

function removeFromCart(productId) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Produk?',
            text: 'Produk ini akan dihapus dari keranjang belanja Anda.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#E53935',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                executeRemoveFromCart(productId);
            }
        });
    } else {
        if (confirm('Hapus produk dari keranjang?')) {
            executeRemoveFromCart(productId);
        }
    }
}

function executeRemoveFromCart(productId) {
    const baseUrl = document.body.getAttribute('data-base-url') || '';
    const cartUrl = `${baseUrl}/controllers/CartController.php?action=remove`;

    const formData = new FormData();
    formData.append('product_id', productId);

    fetch(cartUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        location.reload();
    })
    .catch(err => {
        console.error('Cart Remove Error:', err);
        location.reload();
    });
}

function updateCartBadge(count) {
    const badges = document.querySelectorAll('.cart-badge');
    badges.forEach(badge => {
        badge.textContent = count;
        if (count > 0) {
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    });
}


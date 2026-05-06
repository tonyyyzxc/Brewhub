(() => {
  const cards = Array.from(document.querySelectorAll('.js-product-preview'));
  const backdrop = document.getElementById('bhProductPreview');
  const closeBtn = document.getElementById('bhPreviewClose');
  const img = document.getElementById('bhPreviewImage');
  const title = document.getElementById('bhPreviewTitle');
  const price = document.getElementById('bhPreviewPrice');
  const category = document.getElementById('bhPreviewCategory');
  const shopWrap = document.getElementById('bhPreviewShop');
  const shopLink = document.getElementById('bhPreviewShopLink');
  const shopName = document.getElementById('bhPreviewShopName');
  const description = document.getElementById('bhPreviewDescription');
  const addProductId = document.getElementById('bhPreviewAddProductId');
  const buyProductId = document.getElementById('bhPreviewBuyProductId');

  const addName = document.getElementById('bhPreviewAddProductName');
  const addCategory = document.getElementById('bhPreviewAddProductCategory');
  const addPrice = document.getElementById('bhPreviewAddProductPrice');
  const addImage = document.getElementById('bhPreviewAddProductImage');

  const buyName = document.getElementById('bhPreviewBuyProductName');
  const buyCategory = document.getElementById('bhPreviewBuyProductCategory');
  const buyPrice = document.getElementById('bhPreviewBuyProductPrice');
  const buyImage = document.getElementById('bhPreviewBuyProductImage');

  if (!cards.length || !backdrop || !closeBtn || !img || !title || !price || !category || !description || !addProductId || !buyProductId) {
    return;
  }

  let lastActiveCard = null;

  if (shopLink) {
    shopLink.addEventListener('click', (event) => {
      event.stopPropagation();
      const href = (shopLink.dataset.href || '').trim();
      if (href === '') {
        event.preventDefault();
        return;
      }
      event.preventDefault();
      window.location.assign(href);
    });
  }

  const openPreview = (card) => {
    const data = card.dataset;
    title.textContent = data.name || '';
    price.textContent = data.price || '';
    category.textContent = data.category || '';

    if (shopWrap && shopName) {
      const shopText = (data.shop || '').trim();
      const sellerId = (data.sellerId || '').toString().trim();

      shopWrap.hidden = shopText === '';

      shopName.textContent = shopText;
      if (shopLink) {
        const href = sellerId !== '' && sellerId !== '0'
          ? `ShopPage.php?seller_id=${encodeURIComponent(sellerId)}`
          : '';

        shopLink.dataset.href = href;
        shopLink.href = href || '#';
        shopLink.setAttribute('aria-disabled', href ? 'false' : 'true');
        shopLink.classList.toggle('is-disabled', !href);
      }
    }

    description.value = data.description || '';
    img.src = data.image || '';
    img.alt = data.name || 'Product image';
    addProductId.value = data.productId || '';
    buyProductId.value = data.productId || '';

    if (addName) addName.value = data.name || '';
    if (addCategory) addCategory.value = data.category || '';
    if (addPrice) addPrice.value = (data.rawPrice || data.price || '').toString().replace(/^₱\s*/i, '');
    if (addImage) addImage.value = data.image || '';

    if (buyName) buyName.value = data.name || '';
    if (buyCategory) buyCategory.value = data.category || '';
    if (buyPrice) buyPrice.value = (data.rawPrice || data.price || '').toString().replace(/^₱\s*/i, '');
    if (buyImage) buyImage.value = data.image || '';

    lastActiveCard = card;
    backdrop.hidden = false;
    document.body.classList.add('bh-preview-open');
    closeBtn.focus();
  };

  const closePreview = () => {
    backdrop.hidden = true;
    document.body.classList.remove('bh-preview-open');
    if (lastActiveCard) {
      lastActiveCard.focus();
    }
  };

  cards.forEach((card) => {
    card.addEventListener('click', (event) => {
      if (event.target.closest('form, button, input, select, textarea, a, label')) {
        return;
      }
      openPreview(card);
    });

    card.addEventListener('keydown', (event) => {
      if (event.target.closest('form, button, input, select, textarea, a, label')) {
        return;
      }
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openPreview(card);
      }
    });
  });

  closeBtn.addEventListener('click', closePreview);

  backdrop.addEventListener('click', (event) => {
    if (event.target === backdrop) {
      closePreview();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !backdrop.hidden) {
      closePreview();
    }
  });
})();

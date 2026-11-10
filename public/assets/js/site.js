/* Amélioration progressive : galerie de la fiche produit (sans JavaScript, les miniatures restent visibles). */
document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-gallery-src]') : null;
    if (!button) {
        return;
    }
    var main = document.getElementById('gallery-main');
    if (main) {
        main.src = button.getAttribute('data-gallery-src');
    }
    var all = document.querySelectorAll('[data-gallery-src]');
    for (var i = 0; i < all.length; i++) {
        all[i].setAttribute('aria-current', all[i] === button ? 'true' : 'false');
    }
});

/* Administration : confirmation des actions destructrices (formulaires portant data-confirm). */
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form && form.matches && form.matches('form[data-confirm]')) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    }
});

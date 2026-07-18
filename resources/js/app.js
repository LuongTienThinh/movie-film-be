document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input, textarea').forEach(function (field) {
        field.setAttribute('spellcheck', 'false');
    });

    document.querySelectorAll('aside.sidebar .menu-item > a[href="#"]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            const menuItem = trigger.closest('.menu-item');

            document.querySelectorAll('aside.sidebar .menu-item.open').forEach(function (item) {
                if (item !== menuItem) {
                    item.classList.remove('open');
                }
            });

            menuItem.classList.toggle('open');
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (event) {
            var message = element.getAttribute('data-confirm') || 'Bạn có chắc chắn không?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    window.setTimeout(function () {
        document.querySelectorAll('.admin-alert').forEach(function (alertBox) {
            alertBox.classList.add('is-hidden');
        });
    }, 3500);
});

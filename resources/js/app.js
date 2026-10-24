import './bootstrap';

// SweetAlert2 for deactivation confirmation
document.querySelectorAll('.btn-deactivate').forEach(button => {
    button.addEventListener('click', function () {

        let form = this.closest('form');

        Swal.fire({
            title: 'Are you sure?',
            text: "This user will be deactivated.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, deactivate!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });

    });
});

document.querySelectorAll('.btn-delete').forEach(button => {
    button.addEventListener('click', function () {

        let form = this.closest('form');

        Swal.fire({
            title: 'Delete this role?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });

    });
});


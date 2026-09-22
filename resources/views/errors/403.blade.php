<x-app-layout>


<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'You do not have permission to access this page.',
        confirmButtonColor: '#d33'
    }).then(() => {
        window.location.href = "{{ url()->previous() }}";
    });
});
</script>

</x-app-layout>
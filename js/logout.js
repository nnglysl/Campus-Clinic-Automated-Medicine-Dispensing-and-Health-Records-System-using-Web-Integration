// Logout functionality
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out of your account",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#8b0000',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Logging out...',
                        text: 'Please wait',
                        icon: 'info',
                        timer: 1000,
                        showConfirmButton: false,
                        willClose: () => {
                            window.location.href = '../auth/logout.php';
                        }
                    });
                }
            });
        });
<script>
    // Toastr configuration
    toastr.options = {
        "closeButton": true, // Show close button
        "debug": false, // Disable debug mode
        "progressBar": true, // Show progress bar
        "positionClass": "toast-top-right", // Position of the toast
        "showDuration": "300", // Duration of the show animation
        "hideDuration": "1000", // Duration of the hide animation
        "timeOut": "5000", // Auto-close after 5 seconds
        "extendedTimeOut": "1000", // Additional time if the user hovers over the toast
        "showEasing": "swing", // Easing for show animation
        "hideEasing": "linear", // Easing for hide animation
        "showMethod": "fadeIn", // Show method
        "hideMethod": "fadeOut" // Hide method
    };

    // Display validation errors
    @if ($errors->any())
        @foreach ($errors->all() as $error)
            toastr.error("{{ $error }}");
        @endforeach
    @endif

    // Display session messages
    @if (Session::has('message'))
        const type = "{{ Session::get('alert-type', 'success') }}";
        const message = "{{ Session::get('message') }}";

        switch (type) {
            case 'info':
                toastr.info(message);
                break;
            case 'success':
                toastr.success(message);
                break;
            case 'warning':
                toastr.warning(message);
                break;
            case 'error':
                toastr.error(message);
                break;
            default:
                toastr.success(message); // Default to success if no type is provided
        }
    @endif
</script>

<!-- Dynamic Delete Alert -->
<script>
    ($ => {
        $(() => {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $("body").on("click", ".delete-item", e => {
                e.preventDefault();

                const $this = $(e.currentTarget);
                const deleteUrl = $this.attr("href");

                Swal.fire({
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then(result => {
                    if (result.isConfirmed) {
                        $.ajax({
                            type: "DELETE",
                            url: deleteUrl,
                            success: res => {
                                if (res.status === "success") {
                                    Swal.fire({
                                        title: "Deleted!",
                                        text: res.message,
                                        icon: "success"
                                    });

                                    window.location.reload();
                                } else if (res.status === "error") {
                                    Swal.fire({
                                        title: "Can't Be Deleted!",
                                        text: res.message,
                                        icon: "error"
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.log(error);
                            }
                        });
                    }
                });
            })
        });
    })(jQuery);
</script>

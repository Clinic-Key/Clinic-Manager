document.addEventListener("DOMContentLoaded", function() {
    
    const designationTitles = document.querySelectorAll(".designation-title");

    designationTitles.forEach(title => {
        title.addEventListener("click", function() {
            const doctorsList = this.nextElementSibling;
            if (doctorsList.style.display === "none" || doctorsList.style.display === "") {
                doctorsList.style.display = "block";
                this.querySelector(".arrow").textContent = "▲";
            } else {
                doctorsList.style.display = "none";
                this.querySelector(".arrow").textContent = "▼";
            }
        });

        title.addEventListener("keypress", function(event) {
            if (event.key === "Enter" || event.key === " ") {
                const doctorsList = this.nextElementSibling;
                if (doctorsList.style.display === "none" || doctorsList.style.display === "") {
                    doctorsList.style.display = "block";
                    this.querySelector(".arrow").textContent = "▲";
                } else {
                    doctorsList.style.display = "none";
                    this.querySelector(".arrow").textContent = "▼";
                }
            }
        });
    });
});



jQuery(document).ready(function($) {
    // Add Procedure
    $('#add-procedure').click(function() {
        var $procedure = $('#procedures-wrapper .procedure').first().clone();
        $procedure.find('input, textarea').val('');
        $('#procedures-wrapper').append($procedure);
    });

    // Remove Procedure
    $(document).on('click', '.remove-procedure', function() {
        if ($('#procedures-wrapper .procedure').length > 1) {
            $(this).closest('.procedure').remove();
        } else {
            // If it's the last one, clear its contents
            $(this).closest('.procedure').find('input, textarea').val('');
        }
    });

    // Add Doctor
    $('#add-doctor').click(function() {
        var $doctor = $('#doctors-wrapper .doctor').first().clone();
        $doctor.find('input, textarea').val('');
        $doctor.find('img').remove(); // Remove any existing image previews
        $('#doctors-wrapper').append($doctor);
    });



    // Remove Doctor
    $(document).on('click', '.remove-doctor', function() {
        if ($('#doctors-wrapper .doctor').length > 1) {
            $(this).closest('.doctor').remove();
        } else {
            // If it's the last one, clear its contents
            $(this).closest('.doctor').find('input, textarea').val('');
            $(this).closest('.doctor').find('img').remove(); // Remove the image preview
            $(this).closest('.doctor').find('input[type="file"]').val(''); // Reset file input
            $(this).closest('.doctor').find('input[type="hidden"]').val(''); // Clear hidden fields
        }
    });
});

jQuery(document).ready(function($) {
    const maxImages = 5;
    let selectedFilesArray = []; // Array to keep track of files for preview
    let initialImageOrder = ""; // Variable to track the initial order on page load

    // Function to refresh image order and update hidden input
    function updateImageOrder() {
        let imageOrder = [];
        $(".clinic-image-wrapper").each(function () {
            const imageId = $(this).data("image-id");
            if (imageId) imageOrder.push(imageId);
        });
        $("#clinic_images_order").val(JSON.stringify(imageOrder)); // Update hidden input

        return JSON.stringify(imageOrder);
    }

    // Initialize sortable for reordering images
    $(".clinic-images-container").sortable({
        update: function () {
            updateImageOrder();
        }
    });

    // Remove image from display and update order
    $(".clinic-images-container").on("click", ".remove-image-button", function () {
        const imageId = $(this).parent().data("image-id");

        // Remove the file from selectedFilesArray
        selectedFilesArray = selectedFilesArray.filter(file => file.tempImageId !== imageId);

        $(this).parent().remove();
        updateImageOrder(); // Refresh image order after removal
    });

    // Preview selected images without overriding existing images
    $("#clinic_images").on("change", function (event) {
        const currentImagesCount = $(".clinic-image-wrapper").length;
        const selectedFiles = event.target.files;

        // Check if adding these files will exceed the max limit
        if (currentImagesCount + selectedFiles.length > maxImages) {
            alert("You can only upload a maximum of 5 images.");
            return;
        }

        Array.from(selectedFiles).forEach(file => {
            if (!file.type.startsWith("image/")) return;

            // Add each file with a unique temp ID for tracking
            const tempImageId = `temp-${Math.random().toString(36).substring(2, 9)}`;
            file.tempImageId = tempImageId; // Add a custom property to the file object
            selectedFilesArray.push(file);

            const reader = new FileReader();
            reader.onload = function (e) {
                const imageUrl = e.target.result;

                const imageHtml = `
                    <div class="clinic-image-wrapper" data-image-id="${tempImageId}">
                        <img src="${imageUrl}" alt="Clinic Image" class="clinic-image">
                        <button type="button" class="remove-image-button">×</button>
                    </div>
                `;

                $(".clinic-images-container").append(imageHtml);
                updateImageOrder(); // Refresh image order after adding
            };
            reader.readAsDataURL(file);
        });
        $(this).val('');
    });

    // Capture initial image order on page load
    initialImageOrder = updateImageOrder();

    $('#custom-post-form').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        if ($('input[name="specialty[]"]:checked').length === 0) {
            alert('Please select at least one specialty.');
            return; // Stop execution if validation fails
        }

        // Show the loader
        $('#form-loader').show();

        // Create a FormData object from the form element
        var formData = new FormData(this);

        // Append the action and nonce to the form data
        formData.append('action', 'handle_custom_post_form');
        formData.append('nonce', custom_post_form_vars.nonce);

        // Append selected files to formData
        selectedFilesArray.forEach((file, index) => {
            formData.append(`clinic_images[${index}]`, file);
        });

        // Append the image order
        let currentImageOrder = updateImageOrder();
        formData.append("clinic_images_order", currentImageOrder);

        // Send the AJAX request
        $.ajax({
            url: custom_post_form_vars.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false, // Important for file uploads
            processData: false, // Important for file uploads
            success: function(response) {
                if (response.success) {
                    // Handle success (e.g., display a success message)
                    location.reload();
                    // Optionally, redirect or update the page content
                } else {
                    // Handle server-side validation errors
                    $('#form-loader').hide();
                    alert('An error occurred: ' + response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#form-loader').hide(); // Hide loader
                alert('An unexpected error occurred. Please try again later.');
                console.error('AJAX error:', textStatus, errorThrown);
            }
        });
    });
});

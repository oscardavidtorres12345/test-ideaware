// Example starter JavaScript for disabling form submissions if there are invalid fields
(function () {
  'use strict'

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  var forms = document.querySelectorAll('.needs-validation')

  // Loop over them and prevent submission
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault()
        event.stopPropagation()
        if (form.checkValidity()) {
          form.classList.add('was-validated');
          let check = ($('#check').prop("checked") ? 1 : 0);

          $.ajax({
            type: "post",
            dataType: "json",
            url: "../back/controller.php",
            data: $("#form").serialize() + "&check=" + check
          })
          .done(function(e) {
            alert("Hemos recibido la información, gracias!");
          });
        }
      }, false)
    })
})()

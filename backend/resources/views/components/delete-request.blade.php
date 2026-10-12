{{-- Sends a CSRF-protected DELETE (method spoofing) instead of a GET link. --}}
<script>
    window.deleteRequest = function (url) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';
        [['_token', @json(csrf_token())], ['_method', 'DELETE']].forEach(function (pair) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = pair[0];
            input.value = pair[1];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    };
</script>

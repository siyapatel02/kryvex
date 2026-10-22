@if(session('success'))
    <div class="app-toast success" role="status" aria-live="polite">
        <strong>Success</strong>
        <p>{{ session('success') }}</p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Action Successful', message: @json(session('success')) });
            }
        });
    </script>
@endif

@if(session('error'))
    <div class="app-toast error" role="alert" aria-live="assertive">
        <strong>Error</strong>
        <p>{{ session('error') }}</p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'error', title: 'Action Failed', message: @json(session('error')) });
            }
        });
    </script>
@endif

@if($errors->any())
    <div class="app-toast error" role="alert" aria-live="assertive">
        <strong>Validation Error</strong>
        <p>{{ $errors->first() }}</p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'error', title: 'Validation Error', message: @json($errors->first()) });
            }
        });
    </script>
@endif

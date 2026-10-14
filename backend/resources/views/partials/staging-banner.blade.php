@if(app()->environment('staging'))
    <div data-staging-banner style="width:fit-content;max-width:calc(100% - 2rem);margin:.75rem auto;padding:.35rem .75rem;border:1px solid #d97706;border-radius:999px;background:#fef3c7;color:#713f12;font:700 .75rem/1.4 system-ui,sans-serif;text-align:center;letter-spacing:.03em;">
        Staging · test data
    </div>
@endif

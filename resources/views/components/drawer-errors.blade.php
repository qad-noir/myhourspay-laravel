<div x-cloak x-show="message" x-ref="errorSummary" tabindex="-1" class="dashboard-form-errors mb-4" role="alert">
    <strong x-text="message"></strong>
    <ul><template x-for="(messages, field) in errors" :key="field"><li :id="'drawer-error-' + field" x-text="messages.join(' ')"></li></template></ul>
</div>

<div x-cloak x-show="message" x-ref="errorSummary" tabindex="-1" class="dashboard-form-errors record-drawer__errors" role="alert">
    <strong x-text="message"></strong>
    <ul x-show="Object.keys(errors).length"><template x-for="(messages, field) in errors" :key="field"><li :id="'drawer-error-' + field" x-text="messages.join(' ')"></li></template></ul>
</div>

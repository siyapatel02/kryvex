@extends('admin.layouts.app')

@section('title', 'Notifications')

@section('content')
    <div class="admin-page-head notification-page-head">
        <div>
            <span>Admin Notifications</span>
            <h1>Notification & Email Builder</h1>
            <p>Create user notifications, customize email templates and keep all colors limited to success, warning and alert.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-alert success">{{ session('success') }}</div>
    @endif

    @php
        $groupedTemplates = collect($templates)->groupBy('category');
        $categoryLabels = [
            'inform' => 'Information',
            'success' => 'Success',
            'warning' => 'Warning',
            'alert' => 'Alert',
        ];
    @endphp

    <div class="notification-builder-grid">
        <div class="admin-panel notification-send-panel hover-panel">
            <div class="card-title-row">
                <div>
                    <h2>Send Notification</h2>
                    <p>Select a ready template, edit text and send in-app notification plus optional email.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.notifications.send') }}" class="notification-form" id="notificationSendForm">
                @csrf

                <div class="notification-category-tabs" id="notificationCategoryTabs">
                    @foreach ($categoryLabels as $category => $label)
                        <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-category="{{ $category }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="form-grid">
                    <label>
                        Send To
                        <select name="target" required>
                            <option value="all">All Users</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->uid ?? '' }}">
                                    {{ $user->name ?? 'User' }} — {{ $user->email ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Notification Template
                        <select name="template_key" id="notificationTemplateSelect" required>
                            @foreach ($groupedTemplates as $category => $items)
                                <optgroup label="{{ $categoryLabels[$category] ?? ucfirst($category) }}">
                                    @foreach ($items as $template)
                                        <option value="{{ $template['template_key'] }}" data-category="{{ $template['category'] }}">
                                            {{ $template['label'] }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>
                </div>

                <input type="hidden" name="category" id="notificationCategoryInput" value="inform">

                <label>
                    Notification Type
                    <select name="type" id="notificationTypeSelect" required>
                        <option value="inform">Information</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="alert">Alert</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </label>

                <label>
                    Notification Title
                    <input type="text" name="title" id="notificationTitleInput" maxlength="120" placeholder="Notification title" required>
                </label>

                <label>
                    Notification Message
                    <textarea name="message" id="notificationMessageInput" maxlength="1500" rows="4" placeholder="Write notification message" required></textarea>
                </label>

                <div class="email-editor-card">
                    <div class="email-editor-head">
                        <div>
                            <strong>Email Format</strong>
                            <span>Admin can customize and save email content for every notification type.</span>
                        </div>
                        <label class="notification-checkbox-row">
                            <input type="checkbox" name="send_email" value="1" checked>
                            <span>Send Email</span>
                        </label>
                    </div>

                    <label>
                        Email Subject
                        <input type="text" name="email_subject" id="emailSubjectInput" maxlength="160" required>
                    </label>

                    <label>
                        Email Card Title
                        <input type="text" name="email_title" id="emailTitleInput" maxlength="160" required>
                    </label>

                    <label>
                        Email Body
                        <textarea name="email_body" id="emailBodyInput" maxlength="4000" rows="6" required></textarea>
                    </label>

                    <div class="notification-vars">
                        <span>@{{user}}</span>
                        <span>@{{amount}}</span>
                        <span>@{{status}}</span>
                        <span>@{{reason}}</span>
                        <span>@{{date}}</span>
                        <span>@{{time}}</span>
                        <span>@{{ticket}}</span>
                    </div>
                </div>

                <button type="submit" class="primary-btn">Send Notification</button>
            </form>
        </div>

        <div class="admin-panel hover-panel notification-preview-panel">
            <div class="card-title-row">
                <div>
                    <h2>Live Preview</h2>
                    <p>Preview how the notification email card will look.</p>
                </div>
            </div>

            <div class="notification-device-preview" id="notificationCardPreview">
                <div class="preview-brand">KRYVEX</div>
                <span class="preview-badge warning" id="previewBadge">WARNING</span>
                <p class="preview-hello">Hello User,</p>
                <h3 id="previewTitle">Notification Title</h3>
                <div class="preview-message" id="previewMessage">Your formatted email message preview will appear here.</div>
                <button type="button">Open Kryvex</button>
                <small>Regards,<br>Kryvex Team</small>
            </div>

            <form method="POST" action="{{ route('admin.notifications.templates.save') }}" class="notification-template-save-form" id="notificationTemplateSaveForm">
                @csrf
                <input type="hidden" name="template_key" id="saveTemplateKey">
                <input type="hidden" name="category" id="saveTemplateCategory">
                <input type="hidden" name="type" id="saveTemplateType">
                <input type="hidden" name="label" id="saveTemplateLabel">
                <input type="hidden" name="title" id="saveTemplateTitle">
                <input type="hidden" name="message" id="saveTemplateMessage">
                <input type="hidden" name="email_subject" id="saveTemplateSubject">
                <input type="hidden" name="email_title" id="saveTemplateEmailTitle">
                <input type="hidden" name="email_body" id="saveTemplateEmailBody">
                <button type="submit" class="secondary-btn">Save Current Template</button>
            </form>
        </div>
    </div>

    <div class="admin-panel hover-panel notification-history-panel">
        <div class="card-title-row">
            <div>
                <h2>Recent Notifications</h2>
                <p>Latest notifications sent to users.</p>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>User UID</th>
                        <th>Email</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr>
                            <td><span class="notification-type-pill {{ $notification->type ?? 'inform' }}">{{ ucfirst($notification->type ?? 'inform') }}</span></td>
                            <td>{{ $notification->title ?? '--' }}</td>
                            <td>{{ $notification->message ?? '--' }}</td>
                            <td>{{ $notification->user_uid ?? '--' }}</td>
                            <td>{{ !empty($notification->email_sent_at) ? 'Sent' : (!empty($notification->email_requested) ? 'Pending/Failed' : 'No') }}</td>
                            <td>{{ $notification->created_at ?? '--' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No notifications yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        window.KRYVEX_NOTIFICATION_TEMPLATES = @json($templates);
    </script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const templates = window.KRYVEX_NOTIFICATION_TEMPLATES || {};
    const templateSelect = document.getElementById('notificationTemplateSelect');
    const categoryInput = document.getElementById('notificationCategoryInput');
    const typeSelect = document.getElementById('notificationTypeSelect');
    const titleInput = document.getElementById('notificationTitleInput');
    const messageInput = document.getElementById('notificationMessageInput');
    const emailSubjectInput = document.getElementById('emailSubjectInput');
    const emailTitleInput = document.getElementById('emailTitleInput');
    const emailBodyInput = document.getElementById('emailBodyInput');
    const previewBadge = document.getElementById('previewBadge');
    const previewTitle = document.getElementById('previewTitle');
    const previewMessage = document.getElementById('previewMessage');

    const fields = [titleInput, messageInput, emailSubjectInput, emailTitleInput, emailBodyInput, typeSelect];

    function selectedTemplate() {
        return templates[templateSelect?.value] || Object.values(templates)[0] || {};
    }

    function applyTemplate() {
        const template = selectedTemplate();
        if (!template) return;

        categoryInput.value = template.category || 'inform';
        typeSelect.value = template.type || 'inform';
        titleInput.value = template.title || '';
        messageInput.value = template.message || '';
        emailSubjectInput.value = template.email_subject || template.title || '';
        emailTitleInput.value = template.email_title || template.title || '';
        emailBodyInput.value = template.email_body || template.message || '';

        document.querySelectorAll('#notificationCategoryTabs button').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.category === (template.category || 'inform'));
        });

        updatePreview();
    }

    function updatePreview() {
        const category = categoryInput.value || 'inform';
        previewBadge.textContent = category === 'inform' ? 'INFO' : category.toUpperCase();
        previewBadge.className = `preview-badge ${category}`;
        previewTitle.textContent = emailTitleInput.value || titleInput.value || 'Notification Title';
        previewMessage.innerHTML = (emailBodyInput.value || messageInput.value || 'Your formatted email message preview will appear here.')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');

        document.getElementById('saveTemplateKey').value = templateSelect.value || 'custom_template';
        document.getElementById('saveTemplateCategory').value = category;
        document.getElementById('saveTemplateType').value = typeSelect.value || 'inform';
        document.getElementById('saveTemplateLabel').value = templateSelect.options[templateSelect.selectedIndex]?.text?.trim() || 'Custom Template';
        document.getElementById('saveTemplateTitle').value = titleInput.value;
        document.getElementById('saveTemplateMessage').value = messageInput.value;
        document.getElementById('saveTemplateSubject').value = emailSubjectInput.value;
        document.getElementById('saveTemplateEmailTitle').value = emailTitleInput.value;
        document.getElementById('saveTemplateEmailBody').value = emailBodyInput.value;
    }

    templateSelect?.addEventListener('change', applyTemplate);
    fields.forEach((field) => field?.addEventListener('input', updatePreview));

    document.querySelectorAll('#notificationCategoryTabs button').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('#notificationCategoryTabs button').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            categoryInput.value = button.dataset.category || 'inform';

            const option = [...templateSelect.options].find((item) => item.dataset.category === categoryInput.value);
            if (option) {
                templateSelect.value = option.value;
                applyTemplate();
            }
        });
    });

    applyTemplate();
});
</script>
@endpush

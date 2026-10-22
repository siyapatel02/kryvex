@extends('admin.layouts.app')

@section('title', 'KYC Verification | Kryvex Admin')

@section('content')
@php
    $activeTab = $tab ?? 'pending';
@endphp

<section class="page-head admin-kyc-head">
    <div>
        <p class="eyebrow">Verification Center</p>
        <h1>KYC Verification</h1>
        <span>View Aadhaar card data, ocr checks, webcam selfies, and manage verification approvals.</span>
    </div>
</section>

<section class="admin-kyc-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
    <div class="kyc-stat-card total hover-card">
        <span>Total Users</span>
        <strong>{{ number_format($stats['total'] ?? 0) }}</strong>
        <small>Registered accounts</small>
    </div>
    <div class="kyc-stat-card pending hover-card">
        <span>Pending Review</span>
        <strong style="color: var(--kv-yellow, #facc15);">{{ number_format($stats['pending'] ?? 0) }}</strong>
        <small>Need review</small>
    </div>
    <div class="kyc-stat-card verified hover-card">
        <span>Verified</span>
        <strong style="color: var(--kv-green, #22c55e);">{{ number_format($stats['verified'] ?? 0) }}</strong>
        <small>KYC completed</small>
    </div>
    <div class="kyc-stat-card unverified hover-card">
        <span>Unverified / Rejected</span>
        <strong>{{ number_format($stats['unverified'] ?? 0) }}</strong>
        <small>Not completed</small>
    </div>
</section>

<section class="admin-panel admin-kyc-panel hover-panel">
    <div class="kyc-panel-top">
        <div>
            <h2>KYC Users</h2>
            <p>Review user documents, verify identities, and approve or reject submissions.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.kyc.index') }}" class="admin-kyc-filter-form">
        <div class="admin-kyc-search-shell">
            <span>⌕</span>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, email, user id or status">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
        </div>
        <button class="kyc-search-btn hover-lift" type="submit">Search</button>
        <a class="kyc-clear-btn hover-lift" href="{{ route('admin.kyc.index') }}">Clear</a>
    </form>

    <div class="admin-kyc-tabs">
        <a class="{{ $activeTab === 'pending' ? 'is-active' : '' }}" href="{{ route('admin.kyc.index', ['tab' => 'pending', 'search' => $search]) }}">
            Pending review <span style="display:inline-flex; align-items:center; justify-content:center; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; margin-left:6px; background: {{ $activeTab === 'pending' ? 'rgba(0,0,0,0.25)' : 'rgba(255,255,255,0.1)' }}; color:inherit;">{{ number_format($stats['pending'] ?? 0) }}</span>
        </a>
        <a class="{{ $activeTab === 'verified' ? 'is-active' : '' }}" href="{{ route('admin.kyc.index', ['tab' => 'verified', 'search' => $search]) }}">
            Verified <span style="display:inline-flex; align-items:center; justify-content:center; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; margin-left:6px; background: {{ $activeTab === 'verified' ? 'rgba(0,0,0,0.25)' : 'rgba(255,255,255,0.1)' }}; color:inherit;">{{ number_format($stats['verified'] ?? 0) }}</span>
        </a>
        <a class="{{ $activeTab === 'unverified' ? 'is-active' : '' }}" href="{{ route('admin.kyc.index', ['tab' => 'unverified', 'search' => $search]) }}">
            Unverified <span style="display:inline-flex; align-items:center; justify-content:center; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; margin-left:6px; background: {{ $activeTab === 'unverified' ? 'rgba(0,0,0,0.25)' : 'rgba(255,255,255,0.1)' }}; color:inherit;">{{ number_format($stats['unverified'] ?? 0) }}</span>
        </a>
        <a class="{{ $activeTab === 'all' ? 'is-active' : '' }}" href="{{ route('admin.kyc.index', ['tab' => 'all', 'search' => $search]) }}">
            All <span style="display:inline-flex; align-items:center; justify-content:center; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; margin-left:6px; background: {{ $activeTab === 'all' ? 'rgba(0,0,0,0.25)' : 'rgba(255,255,255,0.1)' }}; color:inherit;">{{ number_format($stats['total'] ?? 0) }}</span>
        </a>
    </div>

    <div class="admin-kyc-table-wrap">
        <table class="admin-kyc-table">
            <thead>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Document</th>
                    <th>Selfie Photo</th>
                    <th>KYC Status</th>
                    <th>Verification</th>
                    <th>Submitted</th>
                    <th>Reviewed</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($kycRows as $row)
                    @php
                        $statusClass = strtolower($row->kyc_status ?? 'unverified');
                        $frontImage = $row->pan_image_url ?? $row->front_image_url ?? '';
                        $backImage = $row->back_image_url ?? '';
                        $selfieImage = $row->selfie_image_url ?? '';
                        $isPending = in_array(strtolower($row->kyc_status), ['pending_review', 'pending_selfie', 'submitted', 'pending']);
                    @endphp
                    <tr>
                        <td>
                            <div class="kyc-user-cell">
                                <div class="kyc-avatar">{{ strtoupper(substr($row->name ?? $row->email ?? 'U', 0, 1)) }}</div>
                                <div>
                                    <strong>{{ $row->name }}</strong>
                                    <span>{{ $row->email }}</span>
                                    <small>ID: {{ $row->user_uid }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 13px;">
                                <div><strong>Aadhaar:</strong> {{ $row->aadhaar_number }}</div>
                                <div><strong>PAN:</strong> {{ $row->pan_number }}</div>
                                <div style="margin-top: 6px;">
                                    <span style="background: #6366f1; color: #ffffff; padding: 3px 9px; border-radius: 12px; font-weight: 700; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; letter-spacing: 0.3px;">
                                        ✓ Verified by DigiLocker
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($selfieImage)
                                <img src="{{ $selfieImage }}" alt="Selfie Photo" data-open-doc-modal data-title="📸 Live Webcam / Portrait Selfie - {{ e($row->name) }}" data-url="{{ $selfieImage }}" data-download-url="{{ $selfieImage }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); cursor: pointer;" title="Click to view Selfie Modal">
                            @else
                                <span class="muted-text" style="font-size: 12px;">No Selfie</span>
                            @endif
                        </td>
                        <td><span class="kyc-status-pill {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $row->kyc_status)) }}</span></td>
                        <td>
                            @if(in_array($row->kyc_status, ['verified', 'approved']))
                                <span class="kyc-ocr-pill matched" style="background: rgba(34,197,94,0.1); color: #22c55e;">Approved</span>
                            @elseif($row->kyc_status === 'pending_review')
                                <span class="kyc-ocr-pill pending" style="background: rgba(250,204,21,0.1); color: #facc15;">Pending Admin</span>
                            @elseif($row->kyc_status === 'rejected')
                                <span class="kyc-ocr-pill rejected" style="background: rgba(239,68,68,0.1); color: #ef4444;">Rejected</span>
                            @else
                                <span class="muted-text">—</span>
                            @endif
                        </td>
                        <td>{{ $row->submitted_at ? \Carbon\Carbon::parse($row->submitted_at)->format('d M Y, h:i A') : '—' }}</td>
                        <td>{{ $row->reviewed_at ? \Carbon\Carbon::parse($row->reviewed_at)->format('d M Y, h:i A') : '—' }}</td>
                        <td class="text-right">
                            <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: nowrap;">
                                <!-- Direct View Aadhaar (Modal Popup on Same Page) -->
                                <button type="button" class="table-action" data-open-doc-modal data-title="Digilocker Verified E-Aadhaar" data-url="{{ route('admin.kyc.download.pdf', ['userUid' => $row->user_uid, 'type' => 'aadhaar']) }}?action=view" data-download-url="{{ route('admin.kyc.download.pdf', ['userUid' => $row->user_uid, 'type' => 'aadhaar']) }}?action=download" style="background: #2563eb; color: #fff; border: none; padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                                    👁️ Aadhaar
                                </button>

                                <!-- Direct View PAN (Modal Popup on Same Page) -->
                                <button type="button" class="table-action" data-open-doc-modal data-title="PAN Verification Record" data-url="{{ route('admin.kyc.download.pdf', ['userUid' => $row->user_uid, 'type' => 'pan']) }}?action=view" data-download-url="{{ route('admin.kyc.download.pdf', ['userUid' => $row->user_uid, 'type' => 'pan']) }}?action=download" style="background: #d97706; color: #fff; border: none; padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                                    💳 PAN
                                </button>

                                <!-- Approve & Reject Buttons inside Row when Pending -->
                                @if($isPending)
                                    <form method="POST" action="{{ route('admin.kyc.approve', $row->user_uid) }}" style="margin: 0; display: inline;">
                                        @csrf
                                        <button type="submit" class="table-action" style="padding: 6px 10px; font-size: 12px; font-weight: 700; color: #fff; background: #10b981; border: none; border-radius: 5px; cursor: pointer; white-space: nowrap;">
                                            ✓ Approve
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.kyc.reject', $row->user_uid) }}" onsubmit="let r = prompt('Reason for rejection:'); if(!r) return false; this.reason.value = r;" style="margin: 0; display: inline;">
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button type="submit" class="table-action" style="padding: 6px 10px; font-size: 12px; font-weight: 700; color: #fff; background: #f59e0b; border: none; border-radius: 5px; cursor: pointer; white-space: nowrap;">
                                            ✕ Reject
                                        </button>
                                    </form>
                                @endif

                                @if(in_array(strtolower($row->kyc_status), ['verified', 'approved', 'rejected', 'pending_review', 'pending_selfie']))
                                    <form method="POST" action="{{ route('admin.kyc.delete', $row->user_uid) }}" onsubmit="return confirm('Are you sure you want to completely delete this user\'s KYC data? This will reset their status to unverified.');" style="margin: 0; display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="table-action danger-btn" style="padding: 6px 10px; font-size: 12px; font-weight: 600; color: #fff; background: #ef4444; border: none; border-radius: 5px; cursor: pointer; white-space: nowrap;">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <strong>No KYC records found</strong>
                                <span>Try another tab or search value.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        @include('admin.components.pagination', ['paginator' => $kycRows])
    </div>
</section>

<div class="kyc-detail-modal" id="kycDetailModal" aria-hidden="true">
    <div class="kyc-detail-backdrop" data-close-kyc-modal></div>
    <div class="kyc-detail-box" role="dialog" aria-modal="true" aria-labelledby="kycDetailTitle" style="max-width: 900px;">
        <button type="button" class="kyc-modal-close" data-close-kyc-modal>×</button>
        <div class="kyc-modal-head">
            <div class="kyc-modal-icon">📄</div>
            <div>
                <h2 id="kycDetailTitle">KYC verification review</h2>
                <p>Review identity document details, uploads, and Webcam portraits.</p>
            </div>
        </div>

        <div class="kyc-detail-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
            <div><span>User</span><strong data-kyc-name>—</strong><small data-kyc-email>—</small></div>
            <div><span>User ID</span><strong data-kyc-uid>—</strong></div>
            <div><span>KYC Status</span><strong data-kyc-status>—</strong></div>
            <div><span>Aadhaar Number</span><strong data-kyc-aadhaar-num>—</strong></div>
            <div><span>PAN Number</span><strong data-kyc-pan-num>—</strong></div>
            <div><span>Compliance Status</span><strong data-kyc-ocr>—</strong></div>
        </div>

        <div class="kyc-document-grid" style="display: block; margin-bottom: 25px;">
            <div class="kyc-document-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; text-align: center;">
                <div style="font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 10px;">📸 Live Webcam / Portrait Selfie</div>
                <img data-kyc-selfie alt="Webcam Portrait Photo" style="max-width: 220px; max-height: 120px; object-fit: contain; display: block; margin: 0 auto; border-radius: 8px; border: 1px solid rgba(255,255,255,0.12);">
                <p data-kyc-selfie-empty style="color: var(--muted-text); margin-top: 10px; font-size: 12px;">No selfie image found.</p>
            </div>
        </div>

        <!-- DIGIO E-DOCUMENT DOWNLOADS (AADHAAR & PAN PDFs) -->
        <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; margin-bottom: 25px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                <h4 style="margin: 0; color: #fff; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <span>🛡️</span> Verified e-Documents (Private Storage: storage/app/private/kyc)
                </h4>
                <span style="font-size: 11px; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 20px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.2);">Secured</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <!-- Aadhaar PDF -->
                <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 14px; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="color: #fff; font-weight: 700; font-size: 13px;">📄 Aadhaar Document</span>
                        <span style="font-size: 12px; color: #94a3b8;" data-modal-aadhaar-masked>Aadhaar</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a id="btnViewAadhaarPdf" href="#" target="_blank" style="flex: 1; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; border-radius: 6px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.12);">
                            👁️ View
                        </a>
                        <a id="btnDownloadAadhaarPdf" href="#" download style="flex: 1; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; border-radius: 6px; background: #2563eb; color: #fff; text-decoration: none; border: none;">
                            ⬇️ Download PDF
                        </a>
                    </div>
                </div>

                <!-- PAN PDF -->
                <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 14px; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="color: #fff; font-weight: 700; font-size: 13px;">💳 PAN Document</span>
                        <span style="font-size: 12px; color: #94a3b8;" data-modal-pan-masked>PAN</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a id="btnViewPanPdf" href="#" target="_blank" style="flex: 1; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; border-radius: 6px; background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.12);">
                            👁️ View
                        </a>
                        <a id="btnDownloadPanPdf" href="#" download style="flex: 1; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; border-radius: 6px; background: #2563eb; color: #fff; text-decoration: none; border: none;">
                            ⬇️ Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="kyc-reject-note" data-kyc-reason-wrap hidden style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong style="color: #ef4444; font-size: 13px; display: block; margin-bottom: 4px;">Previous Reject Reason</strong>
            <p data-kyc-reason style="color: #fff; font-size: 13px; margin: 0;"></p>
        </div>

        <div class="kyc-action-buttons" id="kycActionButtons" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 15px;">
            <div style="display: flex; gap: 10px;">
                <form id="approveKycForm" method="POST" action="" style="flex: 1;">
                    @csrf
                    <button type="submit" class="primary-btn hover-lift" style="width: 100%; height: 40px; background: #10b981; color: #fff; font-weight: 600; border-radius: 6px; border: none; cursor: pointer;">
                        Approve KYC Verification
                    </button>
                </form>
            </div>
            
            <form id="rejectKycForm" method="POST" action="" style="display: flex; flex-direction: column; gap: 10px;">
                @csrf
                <textarea name="reason" placeholder="Enter reason for rejection..." style="width: 100%; min-height: 70px; padding: 10px; border-radius: 6px; background: #141724; border: 1px solid rgba(255,255,255,0.08); color: #fff; outline: none; font-size: 13px;" required></textarea>
                <button type="submit" class="danger-btn hover-lift" style="width: 100%; height: 40px; background: #ef4444; color: #fff; font-weight: 600; border-radius: 6px; border: none; cursor: pointer;">
                    Reject KYC Verification
                </button>
            </form>
        </div>
    </div>
</div>

<!-- SAME PAGE DOCUMENT & SELFIE VIEWER MODAL POPUP -->
<div id="docViewerModal" style="display: none; position: fixed; inset: 0; z-index: 999999; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); align-items: center; justify-content: center; padding: 20px;">
    <div id="docViewerBox" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 16px; width: 100%; max-width: 850px; height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8); transition: all 0.2s ease;">
        <!-- Top Header Bar -->
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: #1e293b; border-bottom: 1px solid rgba(255,255,255,0.1); gap: 15px;">
            <div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
                <span style="font-size: 18px;">📄</span>
                <h3 id="docViewerTitle" style="margin: 0; color: #fff; font-size: 15px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Document Viewer</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                <a id="btnModalDownloadPdf" href="#" class="primary-btn" style="height: 34px; padding: 0 16px; font-size: 12px; font-weight: 700; background: #2563eb; color: #fff; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    ⬇️ Download PDF
                </a>
                <button type="button" id="btnCloseDocViewer" style="background: rgba(255,255,255,0.08); border: none; color: #94a3b8; font-size: 22px; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; line-height: 1;">×</button>
            </div>
        </div>
        <!-- Modal Body (iFrame or Img) -->
        <div id="docViewerBody" style="flex: 1; background: #0b0f19; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 15px;">
            <iframe id="docViewerFrame" src="about:blank" style="width: 100%; height: 100%; border: none; border-radius: 8px;"></iframe>
            <img id="docViewerImg" src="" style="max-width: 100%; max-height: 70vh; width: auto; height: auto; object-fit: contain; display: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);" alt="Selfie Preview">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-open-doc-modal]');
        if (btn) {
            e.preventDefault();
            const url = btn.getAttribute('data-url');
            const title = btn.getAttribute('data-title');
            const downloadUrl = btn.getAttribute('data-download-url');

            document.getElementById('docViewerTitle').textContent = title;
            document.getElementById('btnModalDownloadPdf').href = downloadUrl;

            const box = document.getElementById('docViewerBox');
            const body = document.getElementById('docViewerBody');
            const frame = document.getElementById('docViewerFrame');
            const img = document.getElementById('docViewerImg');

            const isImg = url.startsWith('data:image') || 
                          url.match(/\.(jpeg|jpg|gif|png|webp)($|\?)/i) || 
                          url.includes('/storage/') || 
                          url.includes('storage/app') || 
                          url.includes('selfie') || 
                          (title && title.toLowerCase().includes('selfie'));

            if (isImg) {
                // Selfie / Image View: Perfectly centered box around image without scrollbars
                box.style.width = 'auto';
                box.style.height = 'auto';
                box.style.maxWidth = '600px';
                box.style.maxHeight = '85vh';

                body.style.flex = '0 0 auto';
                body.style.height = 'auto';
                body.style.padding = '20px';
                body.style.overflow = 'hidden';

                frame.style.display = 'none';
                frame.src = 'about:blank';
                img.src = url;
                img.style.display = 'block';
                img.style.maxWidth = '100%';
                img.style.maxHeight = '70vh';
                img.style.width = 'auto';
                img.style.height = 'auto';
                img.style.objectFit = 'contain';
            } else {
                // PDF / Document View
                box.style.width = '95%';
                box.style.height = '88vh';
                box.style.maxWidth = '900px';
                box.style.maxHeight = 'none';

                body.style.flex = '1';
                body.style.height = '100%';
                body.style.padding = '0';

                img.style.display = 'none';
                img.src = '';
                frame.src = url;
                frame.style.display = 'block';
            }

            const modal = document.getElementById('docViewerModal');
            modal.style.display = 'flex';
        }

        if (e.target.closest('#btnCloseDocViewer') || e.target.id === 'docViewerModal') {
            const modal = document.getElementById('docViewerModal');
            modal.style.display = 'none';
            document.getElementById('docViewerFrame').src = 'about:blank';
            document.getElementById('docViewerImg').src = '';
        }
    });
});
</script>
@endsection

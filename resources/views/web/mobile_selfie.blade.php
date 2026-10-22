<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kryvex — Mobile Selfie Verification</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0b0e14;
            --card-bg: #141824;
            --accent-green: #22c55e;
            --accent-blue: #2563eb;
            --text-white: #ffffff;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-white); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 16px; }
        .mobile-card { width: 100%; max-width: 420px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 24px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); text-align: center; }
        .logo-tag { font-size: 11px; font-weight: 700; color: #38bdf8; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 4px; }
        .title { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .subtitle { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
        
        .camera-container { width: 100%; height: 360px; border-radius: 24px; background: #000; overflow: hidden; position: relative; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 15px 35px rgba(0,0,0,0.7); }
        .camera-placeholder { color: var(--text-muted); font-size: 14px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
        
        .btn { width: 100%; height: 50px; border-radius: 14px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s ease; }
        .btn-primary { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #fff; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4); }
        .btn-success { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff; box-shadow: 0 4px 14px rgba(34, 197, 94, 0.4); }
        .btn-secondary { background: rgba(255,255,255,0.08); color: #fff; margin-top: 12px; }
        
        #fileInput { display: none; }
        .success-box { display: none; text-align: center; padding: 20px 0; }
        .success-icon { font-size: 54px; margin-bottom: 12px; }
    </style>
</head>
<body>

    <div class="mobile-card">
        <div class="logo-tag">KRYVEX KYC</div>

        <!-- MAIN STEP FORM -->
        <div id="mainForm">
            <h2 class="title">Live selfie</h2>
            <p class="subtitle">Keep your face in the green circle, then capture.</p>

            <div class="camera-container" id="cameraContainer">
                <video id="videoElement" autoplay playsinline style="display: none; width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                <img id="imagePreview" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                <canvas id="canvasElement" style="display: none;"></canvas>
                
                <!-- Face Oval Overlay & Framing Brackets -->
                <div id="faceOvalOverlayMobile" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; flex-direction: column; align-items: center; justify-content: center;">
                    <svg width="100%" height="100%" viewBox="0 0 320 320" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                        <defs>
                            <mask id="mobileOvalMask">
                                <rect width="100%" height="100%" fill="white" />
                                <ellipse cx="160" cy="145" rx="85" ry="110" fill="black" />
                            </mask>
                        </defs>
                        <rect width="100%" height="100%" fill="rgba(0, 0, 0, 0.55)" mask="url(#mobileOvalMask)" />
                        <!-- Oval Stroke (Turns Green when face detected) -->
                        <ellipse id="mobileOvalStroke" cx="160" cy="145" rx="85" ry="110" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2.5" style="transition: all 0.3s ease;" />
                        <!-- 4 Corner Brackets -->
                        <path d="M 40 55 L 40 32 L 65 32" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M 280 55 L 280 32 L 255 32" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M 40 235 L 40 258 L 65 258" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M 280 235 L 280 258 L 255 258" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                    <!-- Dynamic Status Pill -->
                    <div id="mobileFaceStatusPill" style="position: absolute; bottom: 18px; background: rgba(217, 119, 6, 0.25); border: 1px solid rgba(251, 191, 36, 0.4); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 24px; color: #fef08a; font-size: 12px; font-weight: 600; text-align: center; z-index: 10; transition: all 0.3s ease;">
                        Keep your full face inside the circle
                    </div>
                </div>

                <div class="camera-placeholder" id="placeholder">
                    <span style="font-size: 40px;">📷</span>
                    <span>Tap 'Start Live Camera' to begin</span>
                </div>
            </div>

            <!-- Shutter Lock Instruction & Blue Ring Shutter Button -->
            <div id="shutterContainer" style="display: none; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 16px; gap: 8px;">
                <span id="shutterHint" style="font-size: 12px; color: #94a3b8; font-weight: 500;">Shutter unlocks when face is detected</span>
                
                <button id="btnShutterCapture" type="button" disabled style="width: 68px; height: 68px; border-radius: 50%; border: 4px solid #475569; background: transparent; display: flex; align-items: center; justify-content: center; cursor: not-allowed; opacity: 0.4; transition: all 0.3s ease; outline: none; padding: 0;">
                    <span id="shutterRingInner" style="width: 52px; height: 52px; border-radius: 50%; background: #334155; display: block; transition: all 0.3s ease;"></span>
                </button>
            </div>

            <button type="button" id="btnStartMobileCam" class="btn btn-primary">
                Start Live Camera 📷
            </button>

            <input type="file" id="fileInput" accept="image/*" capture="user">

            <label for="fileInput" id="lblNativeCam" style="display: block; text-align: center; font-size: 12px; color: #64748b; margin-top: 12px; cursor: pointer; text-decoration: underline;">
                Having trouble? Use system camera app 📷
            </label>

            <button type="button" id="btnUpload" class="btn btn-success" style="display: none; margin-top: 12px;">
                Submit &amp; Upload Selfie ❯
            </button>

            <button type="button" id="btnRetake" class="btn btn-secondary" style="display: none;">
                Retake Photo 🔄
            </button>
        </div>

        <!-- SUCCESS SCREEN -->
        <div class="success-box" id="successBox">
            <div class="success-icon">🎉</div>
            <h2 class="title" style="color: #4ade80;">Selfie Uploaded!</h2>
            <p class="subtitle" style="margin-top: 8px;">Your selfie has been received. Your desktop screen will update automatically within 1-2 seconds.</p>
            <p style="font-size: 12px; color: #64748b;">You can now close this page on your mobile phone.</p>
        </div>
    </div>

    <script>
        const token = "{{ $token }}";
        let capturedBlob = null;
        let webcamStream = null;
        let detectionTimer = null;
        let isDetected = false;

        const videoEl = document.getElementById('videoElement');
        const imgPreview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('placeholder');
        const fileInput = document.getElementById('fileInput');

        const btnStartMobileCam = document.getElementById('btnStartMobileCam');
        const lblNativeCam = document.getElementById('lblNativeCam');
        const shutterContainer = document.getElementById('shutterContainer');
        const btnShutterCapture = document.getElementById('btnShutterCapture');
        const shutterRingInner = document.getElementById('shutterRingInner');
        const mobileOvalStroke = document.getElementById('mobileOvalStroke');
        const mobileFaceStatusPill = document.getElementById('mobileFaceStatusPill');
        const faceOvalOverlayMobile = document.getElementById('faceOvalOverlayMobile');

        const btnUpload = document.getElementById('btnUpload');
        const btnRetake = document.getElementById('btnRetake');
        const mainForm = document.getElementById('mainForm');
        const successBox = document.getElementById('successBox');

        let capturedBase64 = null;

        btnStartMobileCam.onclick = async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false
                });
                webcamStream = stream;
                videoEl.srcObject = stream;
                videoEl.style.display = 'block';
                placeholder.style.display = 'none';
                faceOvalOverlayMobile.style.display = 'flex';
                shutterContainer.style.display = 'flex';
                btnStartMobileCam.style.display = 'none';
                lblNativeCam.style.display = 'none';

                if (detectionTimer) clearInterval(detectionTimer);
                detectionTimer = setInterval(processMobileFrame, 150);

            } catch (err) {
                // Fallback to system camera app if WebRTC is blocked
                fileInput.click();
            }
        };

        function processMobileFrame() {
            if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight || !webcamStream) return;

            try {
                const canvas = document.createElement('canvas');
                canvas.width = 200;
                canvas.height = 200;
                const ctx = canvas.getContext('2d');

                const vWidth = videoEl.videoWidth;
                const vHeight = videoEl.videoHeight;

                const containerAspect = 1.0;
                const videoAspect = vWidth / vHeight;

                let sx = 0, sy = 0, sWidth = vWidth, sHeight = vHeight;
                if (videoAspect > containerAspect) {
                    sWidth = vHeight * containerAspect;
                    sx = (vWidth - sWidth) / 2;
                } else {
                    sHeight = vWidth / containerAspect;
                    sy = (vHeight - sHeight) / 2;
                }

                ctx.drawImage(videoEl, sx, sy, sWidth, sHeight, 0, 0, 200, 200);

                const imgData = ctx.getImageData(0, 0, 200, 200);
                const data = imgData.data;

                const ovalCx = 100;
                const ovalCy = 91;
                const ovalRx = 53;
                const ovalRy = 68;

                let ovalTotal = 0;
                let ovalSkin = 0;
                let borderTotal = 0;
                let borderSkin = 0;
                let luminanceValues = [];

                // 4 Quadrants inside oval
                let q1Skin = 0, q1Total = 0; // Top-Left
                let q2Skin = 0, q2Total = 0; // Top-Right
                let q3Skin = 0, q3Total = 0; // Bottom-Left
                let q4Skin = 0, q4Total = 0; // Bottom-Right

                // Forehead skin reference luminance
                let foreheadLumSum = 0;
                let foreheadSkinCount = 0;

                // Eye region contrast pixel counts
                let leftEyeContrastCount = 0;
                let rightEyeContrastCount = 0;

                for (let y = 0; y < 200; y++) {
                    for (let x = 0; x < 200; x++) {
                        const idx = (y * 200 + x) * 4;
                        const r = data[idx];
                        const g = data[idx + 1];
                        const b = data[idx + 2];

                        const dx = (x - ovalCx) / ovalRx;
                        const dy = (y - ovalCy) / ovalRy;
                        const insideOval = (dx * dx + dy * dy) <= 1.0;

                        const isBorder = (x < 18 || x > 182 || y < 18 || y > 182);

                        // RGB Skin Bounds
                        const isSkinRGB = (r > 75 && g > 30 && b > 15 && (Math.max(r, g, b) - Math.min(r, g, b) > 12) && Math.abs(r - g) > 10 && r > g && r > b);
                        
                        // YCbCr Skin Bounds
                        const cbVal = -0.1687 * r - 0.3313 * g + 0.5 * b + 128;
                        const crVal = 0.5 * r - 0.4187 * g - 0.0813 * b + 128;
                        const isSkinYCbCr = (cbVal >= 75 && cbVal <= 135 && crVal >= 130 && crVal <= 175);

                        // HSV Bounds
                        const maxRGB = Math.max(r, g, b) / 255;
                        const minRGB = Math.min(r, g, b) / 255;
                        const delta = maxRGB - minRGB;
                        let hue = 0;
                        if (delta > 0) {
                            if (maxRGB === r / 255) hue = ((g - b) / 255 / delta) % 6;
                            else if (maxRGB === g / 255) hue = (b - r) / 255 / delta + 2;
                            else hue = (r - g) / 255 / delta + 4;
                            hue = Math.round(hue * 60);
                            if (hue < 0) hue += 360;
                        }
                        const sat = maxRGB > 0 ? (delta / maxRGB) : 0;
                        const isSkinHSV = ((hue >= 0 && hue <= 50) || (hue >= 335 && hue <= 360)) && (sat >= 0.12 && sat <= 0.68);

                        const isSkin = (isSkinRGB && isSkinYCbCr && isSkinHSV);

                        if (isBorder) {
                            borderTotal++;
                            if (isSkin) borderSkin++;
                        }

                        if (insideOval) {
                            ovalTotal++;
                            const lum = 0.299 * r + 0.587 * g + 0.114 * b;

                            if (isSkin) {
                                ovalSkin++;
                                luminanceValues.push(lum);

                                // Forehead reference skin (x: 80..120, y: 35..55)
                                if (x >= 80 && x <= 120 && y >= 35 && y <= 55) {
                                    foreheadLumSum += lum;
                                    foreheadSkinCount++;
                                }
                            }

                            // 4 Quadrants
                            if (y < ovalCy) {
                                if (x < ovalCx) { q1Total++; if (isSkin) q1Skin++; }
                                else { q2Total++; if (isSkin) q2Skin++; }
                            } else {
                                if (x < ovalCx) { q3Total++; if (isSkin) q3Skin++; }
                                else { q4Total++; if (isSkin) q4Skin++; }
                            }
                        }
                    }
                }

                const avgForeheadLum = foreheadSkinCount > 5 ? (foreheadLumSum / foreheadSkinCount) : 125;

                // Eye Feature Contrast Check (Left & Right Eye Boxes)
                for (let y = 55; y <= 88; y++) {
                    for (let x = 60; x <= 140; x++) {
                        if (x > 90 && x < 110) continue;
                        const idx = (y * 200 + x) * 4;
                        const lum = 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                        
                        if (lum < avgForeheadLum * 0.88 || lum < 110) {
                            if (x <= 90) leftEyeContrastCount++;
                            else rightEyeContrastCount++;
                        }
                    }
                }

                const ovalRatio = ovalTotal > 0 ? (ovalSkin / ovalTotal) * 100 : 0;
                const borderRatio = borderTotal > 0 ? (borderSkin / borderTotal) * 100 : 0;

                // Contrast Variance
                let contrastVariance = 0;
                if (luminanceValues.length > 25) {
                    const avgLum = luminanceValues.reduce((a, b) => a + b, 0) / luminanceValues.length;
                    const sqDiffs = luminanceValues.map(v => Math.pow(v - avgLum, 2));
                    contrastVariance = Math.sqrt(sqDiffs.reduce((a, b) => a + b, 0) / luminanceValues.length);
                }

                const q1Ratio = q1Total > 0 ? (q1Skin / q1Total) : 0;
                const q2Ratio = q2Total > 0 ? (q2Skin / q2Total) : 0;
                const q3Ratio = q3Total > 0 ? (q3Skin / q3Total) : 0;
                const q4Ratio = q4Total > 0 ? (q4Skin / q4Total) : 0;

                const topSymmetry = Math.max(q1Skin, q2Skin) / Math.max(1, Math.min(q1Skin, q2Skin));
                const botSymmetry = Math.max(q3Skin, q4Skin) / Math.max(1, Math.min(q3Skin, q4Skin));

                const hasFacialFeatures = contrastVariance >= 7.0;
                const hasQuadrantSkin = (q1Ratio >= 0.10 && q2Ratio >= 0.10 && q3Ratio >= 0.10 && q4Ratio >= 0.10);
                const hasSymmetry = (topSymmetry <= 1.80 && botSymmetry <= 1.80);
                const hasBothEyes = (leftEyeContrastCount >= 1 && rightEyeContrastCount >= 1) || (leftEyeContrastCount + rightEyeContrastCount >= 3);

                isDetected = (ovalRatio >= 18.0 && borderRatio <= 32.0 && hasFacialFeatures && hasQuadrantSkin && hasSymmetry && hasBothEyes);

                if (isDetected) {
                    mobileOvalStroke.setAttribute('stroke', '#22c55e');
                    mobileOvalStroke.style.filter = 'drop-shadow(0 0 10px rgba(34, 197, 94, 0.9))';

                    mobileFaceStatusPill.style.background = 'rgba(22, 101, 52, 0.85)';
                    mobileFaceStatusPill.style.borderColor = 'rgba(74, 222, 128, 0.6)';
                    mobileFaceStatusPill.style.color = '#4ade80';
                    mobileFaceStatusPill.innerHTML = '✓ Face detected — Keep position steady';

                    btnShutterCapture.disabled = false;
                    btnShutterCapture.style.cursor = 'pointer';
                    btnShutterCapture.style.opacity = '1';
                    btnShutterCapture.style.borderColor = '#2563eb';
                    btnShutterCapture.style.boxShadow = '0 0 20px rgba(37, 99, 235, 0.6)';
                    shutterRingInner.style.background = '#38bdf8';
                    shutterRingInner.style.transform = 'scale(1.05)';
                } else {
                    mobileOvalStroke.setAttribute('stroke', 'rgba(255,255,255,0.6)');
                    mobileOvalStroke.style.filter = 'none';

                    mobileFaceStatusPill.style.background = 'rgba(217, 119, 6, 0.25)';
                    mobileFaceStatusPill.style.borderColor = 'rgba(251, 191, 36, 0.4)';
                    mobileFaceStatusPill.style.color = '#fef08a';

                    if (!hasBothEyes || !hasQuadrantSkin) {
                        mobileFaceStatusPill.innerHTML = 'Keep full face uncovered (remove hand/hair)';
                    } else if (!hasSymmetry) {
                        mobileFaceStatusPill.innerHTML = 'Look straight at camera (no side face)';
                    } else {
                        mobileFaceStatusPill.innerHTML = 'Keep your full face inside the circle';
                    }

                    btnShutterCapture.disabled = true;
                    btnShutterCapture.style.cursor = 'not-allowed';
                    btnShutterCapture.style.opacity = '0.4';
                    btnShutterCapture.style.borderColor = '#475569';
                    btnShutterCapture.style.boxShadow = 'none';
                    shutterRingInner.style.background = '#334155';
                    shutterRingInner.style.transform = 'scale(1)';
                }

            } catch (e) {}
        }

        btnShutterCapture.onclick = () => {
            if (!isDetected || !videoEl || !webcamStream) return;

            const canvas = document.createElement('canvas');
            canvas.width = videoEl.videoWidth || 640;
            canvas.height = videoEl.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);

            stopMobileWebcam();
            showCapturedPreview(canvas.toDataURL('image/jpeg', 0.9));
        };

        function stopMobileWebcam() {
            if (detectionTimer) {
                clearInterval(detectionTimer);
                detectionTimer = null;
            }
            if (webcamStream) {
                webcamStream.getTracks().forEach(t => t.stop());
                webcamStream = null;
            }
        }

        const draftKey = 'kyc_selfie_draft_' + token;

        // Full-size phone photos exceed the server's 8MB POST limit, so downscale before upload.
        function compressDataUrl(dataUrl, maxSide = 1280, quality = 0.85) {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => {
                    const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * scale);
                    canvas.height = Math.round(img.height * scale);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                img.onerror = () => resolve(dataUrl);
                img.src = dataUrl;
            });
        }

        function showCapturedPreview(dataUrl) {
            capturedBase64 = dataUrl;
            imgPreview.src = dataUrl;
            imgPreview.style.display = 'block';
            placeholder.style.display = 'none';
            videoEl.style.display = 'none';
            faceOvalOverlayMobile.style.display = 'none';
            shutterContainer.style.display = 'none';
            if (lblNativeCam) lblNativeCam.style.display = 'none';
            if (btnStartMobileCam) btnStartMobileCam.style.display = 'none';
            btnUpload.style.display = 'flex';
            btnRetake.style.display = 'flex';
            try { sessionStorage.setItem(draftKey, dataUrl); } catch (e) {}
        }

        try {
            const draft = sessionStorage.getItem(draftKey);
            if (draft) showCapturedPreview(draft);
        } catch (e) {}

        fileInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                capturedBlob = file;
                const reader = new FileReader();
                reader.onload = async (evt) => {
                    showCapturedPreview(await compressDataUrl(evt.target.result));
                };
                reader.readAsDataURL(file);
            }
        };

        btnRetake.onclick = () => {
            stopMobileWebcam();
            capturedBlob = null;
            capturedBase64 = null;
            try { sessionStorage.removeItem(draftKey); } catch (e) {}
            fileInput.value = '';
            imgPreview.style.display = 'none';
            placeholder.style.display = 'flex';
            btnUpload.style.display = 'none';
            btnRetake.style.display = 'none';
            shutterContainer.style.display = 'none';
            faceOvalOverlayMobile.style.display = 'none';
            if (btnStartMobileCam) btnStartMobileCam.style.display = 'flex';
            if (lblNativeCam) lblNativeCam.style.display = 'block';
        };

        btnUpload.onclick = async () => {
            const base64Data = capturedBase64 || (imgPreview.src && imgPreview.src.startsWith('data:') ? imgPreview.src : null);

            if (!base64Data) {
                alert('Please capture a selfie photo first.');
                return;
            }

            btnUpload.disabled = true;
            btnUpload.textContent = 'Uploading...';

            try {
                const response = await fetch('/api/kyc/mobile-selfie-upload', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token,
                        image_base64: base64Data
                    })
                });

                const rawText = await response.text();
                let data = null;
                try {
                    data = JSON.parse(rawText);
                } catch (e) {}

                if (data && data.success) {
                    try { sessionStorage.removeItem(draftKey); } catch (e) {}
                    mainForm.style.display = 'none';
                    successBox.style.display = 'block';
                    return;
                }

                const message = (data && data.message)
                    || (response.status === 413 ? 'Photo is too large. Please retake the selfie.' : `Upload failed (HTTP ${response.status}). Please try again.`);
                alert(message);
            } catch (e) {
                alert('Network error while uploading. Check that your phone is on the same Wi-Fi and try again.');
            }
            btnUpload.disabled = false;
            btnUpload.textContent = 'Submit & Upload Selfie ❯';
        };
    </script>
</body>
</html>

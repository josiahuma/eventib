<x-app-layout>
    {{-- Cropper CSS --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Your event avatar — {{ $event->name }}
            </h2>
            <a href="{{ route('events.show', $event) }}"
               class="text-sm text-gray-600 hover:text-gray-800 underline">Back to event</a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left: Tools --}}
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">1. Choose your photo</label>
                        <input type="file" id="userImageInput" disabled accept="image/*" class="mt-1 w-full rounded-lg border-gray-300">
                        <p class="text-xs text-gray-500 mt-1">Drag or pinch to frame your photo. Then tap “Use photo”.</p>
                    </div>

                    <div class="pt-2 border-t">
                        <button id="btnDownload"
                                class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition disabled:opacity-50"
                                disabled>
                            Download PNG
                        </button>
                        <button type="button" id="btnShare" class="avatar-secondary" disabled>Save / share image</button>
                        <button type="button" id="btnEdit" class="avatar-secondary" disabled>Adjust photo crop</button>
                        <div id="exportPreview" hidden><img id="exportImage" alt="Your finished event avatar"><a id="openImage" target="_blank" rel="noopener">Open full image</a><p>On iPhone, use Save / share image. You can also touch and hold the finished image to see saving options.</p></div>
                        <p id="avatarStatus" role="status" aria-live="polite" class="text-sm text-gray-600 mt-3">Choose a photo to get started. Your photo stays in your browser.</p>
                    </div>
                </div>
            </div>

            {{-- Right: Canvas --}}
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
                    <div class="mb-3 text-sm text-gray-600">
                        2. Your photo fits automatically. To change your framing, tap “Adjust photo crop”. Then download or share your finished avatar.
                    </div>
                    <div id="canvas-wrap" class="w-full overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3">
                        <canvas id="avatar-canvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cropper Modal --}}
    <div id="cropperModal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="cropTitle">
        <div class="avatar-dialog">
            <div class="p-3 border-b flex items-center justify-between">
                <h3 id="cropTitle" class="font-semibold text-gray-800 text-sm">Frame your photo</h3>
                <button id="cropCancel" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</button>
            </div>

            <div class="modal-body" id="cropArea">
                <img id="cropperImage" alt="Crop">

            </div>

            <div class="avatar-crop-footer">
                <p>Drag to move · pinch to zoom</p>
                <div class="avatar-zoom"><button type="button" id="zoomOut" aria-label="Zoom out">−</button><button type="button" id="cropReset">Reset</button><button type="button" id="zoomIn" aria-label="Zoom in">+</button></div>
                <button id="cropUse"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                    Use photo
                </button>
            </div>
        </div>
    </div>

    <style>
        #cropperModal { position:fixed; left:0; top:0; width:100%; height:100vh; height:100dvh; z-index:2147483000; background:rgba(15,23,42,.8); align-items:center; justify-content:center; padding:12px; box-sizing:border-box; }
        #cropperModal.hidden { display:none; }
        .avatar-dialog { width:min(100%,680px); height:min(760px,100%); display:flex; flex-direction:column; background:white; border-radius:24px; overflow:hidden; box-shadow:0 24px 80px #0005; }
        .avatar-dialog > :first-child { flex:none; padding:16px; }
        #cropArea { flex:1; min-height:0; position:relative; background:#111827; overflow:hidden; }
        #cropperImage { display:block; max-width:100%; max-height:100%; }
        #cropArea .cropper-container { width:100%!important; height:100%!important; }
        #cropArea .cropper-view-box, #cropArea .cropper-face { border-radius:50%; }
        #cropArea .cropper-view-box { outline:0; box-shadow:0 0 0 2px white; }
        #cropArea .cropper-dashed, #cropArea .cropper-line, #cropArea .cropper-point { display:none; }
        .avatar-crop-footer { flex:none; background:white; padding:12px 16px max(16px,env(safe-area-inset-bottom)); display:grid; gap:10px; text-align:center; }
        .avatar-crop-footer p { font-size:13px; color:#64748b; margin:0; }
        .avatar-zoom { display:flex; gap:8px; justify-content:center; }
        .avatar-zoom button { min-width:48px; min-height:44px; border:1px solid #e2e8f0; border-radius:12px; padding:8px 16px; font-weight:600; }
        #cropUse { display:block; width:100%; min-height:48px; justify-content:center; background:#e84b4b; border-radius:12px; font-size:16px; font-weight:700; }
        .avatar-secondary { display:block; width:100%; padding:12px; margin-top:10px; min-height:44px; border:1px solid #e2e8f0; border-radius:12px; color:#334155; font-weight:600; background:white; }
        .avatar-secondary:disabled { opacity:.45; }
        #btnDownload { background:#e84b4b; min-height:48px; }
        #exportPreview { margin-top:14px; padding:12px; border-radius:14px; background:#f8fafc; }
        #exportPreview img { width:100%; border-radius:8px; }
        #exportPreview a { display:block; text-align:center; padding:12px; text-decoration:underline; }
        #exportPreview p { font-size:13px; color:#64748b; }
        #avatar-canvas { display:block; }
        body.avatar-cropping > :not(#cropperModal) { visibility:hidden!important; }
        @media(max-width:640px) { #cropperModal { padding:8px; } .avatar-dialog { border-radius:18px; } }
    </style>

    {{-- Libs BEFORE our custom script --}}
    <script src="https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

    <script>
    (function () {
        const bgUrl = @json(asset('storage/' . $event->avatar_url));

        // Fabric canvas — let Fabric do retina scaling
        const canvas = new fabric.Canvas('avatar-canvas', {
            selection: false,
            preserveObjectStacking: true,
            enableRetinaScaling: true,
            allowTouchScrolling: true
        });
        let bgImg = null;
        let userImgObj = null;
        let photoFrame = null;

        // Largest enclosed transparent region, measured once per template.
        // Normalised coordinates keep the same fit on every screen and export size.
        function findPhotoFrame(data, width, height) {
            const count = width * height;
            const visited = new Uint8Array(count);
            const queue = new Int32Array(count);
            let best = null;
            for (let start = 0; start < count; start++) {
                if (visited[start] || data[start * 4 + 3] >= 128) continue;
                let head = 0, tail = 1, area = 0;
                queue[0] = start; visited[start] = 1;
                let minX = width, minY = height, maxX = 0, maxY = 0, touchesEdge = false;
                while (head < tail) {
                    const p = queue[head++], x = p % width, y = Math.floor(p / width);
                    area++;
                    minX = Math.min(minX,x); maxX = Math.max(maxX,x);
                    minY = Math.min(minY,y); maxY = Math.max(maxY,y);
                    if (x === 0 || y === 0 || x === width-1 || y === height-1) touchesEdge = true;
                    const visit = n => {
                        if (!visited[n] && data[n*4+3] < 128) { visited[n] = 1; queue[tail++] = n; }
                    };
                    if (x > 0) visit(p-1);
                    if (x < width-1) visit(p+1);
                    if (y > 0) visit(p-width);
                    if (y < height-1) visit(p+width);
                }
                if (!touchesEdge && area > count*.01 && (!best || area > best.area)) best = {area,minX,minY,maxX,maxY};
            }
            if (!best) return null;
            // Extend the backing photo slightly beneath the rim to cover antialiased edges.
            const left = Math.max(0,best.minX-4), top = Math.max(0,best.minY-4);
            const right = Math.min(width,best.maxX+5), bottom = Math.min(height,best.maxY+5);
            return {x:(left+right)/2/width, y:(top+bottom)/2/height, width:(right-left)/width, height:(bottom-top)/height};
        }
        function measureFrame(image) {
            const probe = document.createElement('canvas');
            const factor = Math.min(1,512 / Math.max(image.naturalWidth,image.naturalHeight));
            probe.width = Math.max(1,Math.round(image.naturalWidth*factor));
            probe.height = Math.max(1,Math.round(image.naturalHeight*factor));
            const context = probe.getContext('2d', {willReadFrequently:true});
            context.drawImage(image,0,0,probe.width,probe.height);
            return findPhotoFrame(context.getImageData(0,0,probe.width,probe.height).data,probe.width,probe.height);
        }
        function fitPhotoToFrame() {
            if (!userImgObj || !photoFrame) return;
            const width = canvas.getWidth(), height = canvas.getHeight();
            const scale = Math.max(photoFrame.width*width/userImgObj.width,photoFrame.height*height/userImgObj.height);
            userImgObj.set({left:photoFrame.x*width, top:photoFrame.y*height, scaleX:scale, scaleY:scale});
            userImgObj.setCoords();
        }

        function resizeCanvas() {
            if (!bgImg) return;

            const wrap = document.getElementById('canvas-wrap');
            const rect = wrap.getBoundingClientRect();

            // inner content width (paddings are already excluded from content box width? Tailwind uses content-box)
            const cs   = getComputedStyle(wrap);
            const padX = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
            const padY = parseFloat(cs.paddingTop)  + parseFloat(cs.paddingBottom);

            const innerW = Math.max(1, Math.floor(rect.width - padX - 2));


            // keep original image aspect
            const ratio = bgImg.height / bgImg.width;
            const cw = innerW;
            const ch = Math.round(cw * ratio);

            // Make the wrapper tall enough so nothing gets clipped
            wrap.style.height = `${ch + padY}px`;

            // Set canvas dimensions (Fabric sets CSS + backstore correctly)
            canvas.setDimensions({ width: cw, height: ch });

            // Scale bg image to fill canvas exactly
            const scale = cw / bgImg.width;
            canvas.setOverlayImage(
                bgImg,
                canvas.renderAll.bind(canvas),
                { originX: 'left', originY: 'top', scaleX: scale, scaleY: scale, objectCaching: false }
            );

            fitPhotoToFrame();
            canvas.requestRenderAll();
            if (userImgObj) queueExport();
        }

        let raf;
        window.addEventListener('resize', () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(resizeCanvas);
        });

        // Elements
        const input = document.getElementById('userImageInput');
        const btnDownload = document.getElementById('btnDownload');
        const modal = document.getElementById('cropperModal');
        const cropImg = document.getElementById('cropperImage');
        const cropUse = document.getElementById('cropUse');
        const cropCancel = document.getElementById('cropCancel');

        const status = document.getElementById('avatarStatus');
        const btnShare = document.getElementById('btnShare');
        const btnEdit = document.getElementById('btnEdit');
        const preview = document.getElementById('exportPreview');
        let cropper = null, photoUrl = null, previousFocus = null, oldOverflow = '', exportUrl = null, exportFile = null, exportRevision = 0, exportTimer;
        const fileName = @json((\Illuminate\Support\Str::slug($event->name) ?: 'event') . '_display_picture.png');

        fabric.Image.fromURL(bgUrl, (img) => {
            if (!img || !img.width) { status.textContent = "The event artwork could not load. Please reload the page."; return; }
            bgImg = img;
            try { photoFrame = measureFrame(img.getElement()); }
            catch (error) { console.error(error); }
            if (!photoFrame) {
                status.textContent = 'This avatar template needs one transparent photo opening. Ask the organiser to upload a PNG template with a transparent frame.';
                resizeCanvas();
                return;
            }
            input.disabled = false;
            resizeCanvas();
        }, { crossOrigin: 'anonymous' });

        function openModal() {
            if (modal.parentElement !== document.body) document.body.appendChild(modal);
            previousFocus = document.activeElement;
            oldOverflow = document.body.style.overflow;
            modal.classList.remove('hidden'); modal.style.display = 'flex';
            document.body.classList.add('avatar-cropping');
            syncViewport();
            cropCancel.focus();
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            modal.classList.add('hidden'); modal.style.display = '';
            document.body.classList.remove('avatar-cropping');
            document.body.style.overflow = oldOverflow;
            if (previousFocus) previousFocus.focus();
        }

        function fitCropperToContainer() {
            if (!cropper) return;
            const c = cropper.getContainerData();
            const i = cropper.getImageData();
            if (!c.width || !c.height || !i.naturalWidth || !i.naturalHeight) return;

            const size = Math.min(c.width, c.height) * 0.82;
            const fitScale = Math.max(size / i.naturalWidth, size / i.naturalHeight);
            cropper.reset();
            const startScale = fitScale;
            cropper.zoomTo(startScale, { x: c.width / 2, y: c.height / 2 });


            cropper.setCropBoxData({
                width: size, height: size,
                left: (c.width - size) / 2,
                top:  (c.height - size) / 2
            });
        }

        function loadPhoto(file) {
            if (!file || !bgImg) return;
            if (cropper) { cropper.destroy(); cropper = null; }
            if (photoUrl) URL.revokeObjectURL(photoUrl);

            const url = photoUrl = URL.createObjectURL(file);
            cropImg.onload = () => {
                openModal();
                requestAnimationFrame(() => {
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(cropImg, {
                        viewMode: 1,
                        center: false,
                        dragMode: 'move',
                        movable: true,
                        zoomable: true,
                        zoomOnWheel: true,
                        zoomOnTouch: true,
                        wheelZoomRatio: 0.1,
                        background: false,
                        guides: false,
                        highlight: false,
                        toggleDragModeOnDblclick: false,
                        aspectRatio: 1,
                        autoCrop: true,
                        autoCropArea: 0.7,
                        cropBoxMovable: false,
                        cropBoxResizable: false,
                        ready() { fitCropperToContainer(true); cropUse.disabled = false; }
                    });
                });
            };
            cropUse.disabled = true;
            cropImg.onerror = () => { status.textContent = 'This photo could not be opened. Try a JPEG or PNG image.'; closeModal(); };
            cropImg.src = url;
        }
        input.addEventListener('change', e => loadPhoto(e.target.files && e.target.files[0]));
        btnEdit.addEventListener('click', () => { const file = input.files && input.files[0]; if (file) loadPhoto(file); });
        document.getElementById('zoomIn').addEventListener('click', () => cropper && cropper.zoom(.1));
        document.getElementById('zoomOut').addEventListener('click', () => cropper && cropper.zoom(-.1));
        document.getElementById('cropReset').addEventListener('click', () => fitCropperToContainer());
        function syncViewport() {
            const v = window.visualViewport;
            modal.style.height = `${v ? v.height : window.innerHeight}px`;
            modal.style.top = `${v ? v.offsetTop : 0}px`;
        }
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', syncViewport);
            window.visualViewport.addEventListener('scroll', syncViewport);
        }
        modal.addEventListener('keydown', e => {
            if (e.key === 'Escape') { cropCancel.click(); return; }
            if (e.key !== 'Tab') return;
            const buttons = [...modal.querySelectorAll('button:not(:disabled)')];
            const first = buttons[0], last = buttons[buttons.length-1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });

        window.addEventListener('resize', () => {
            if (!cropper) return;
            requestAnimationFrame(() => fitCropperToContainer(false));
        });

        cropCancel.addEventListener('click', () => {
            try { cropper && cropper.destroy(); } catch {}
            cropper = null;
            closeModal();

        });

        cropUse.addEventListener('click', () => {
            if (!cropper || !bgImg) return;
            try {
            cropUse.disabled = true;
            const SIZE = 1400;
            const square = cropper.getCroppedCanvas({
                width: SIZE, height: SIZE,
                imageSmoothingEnabled: true, imageSmoothingQuality: 'high'
            });

            if (!square) throw new Error('Photo crop unavailable');
            const img = new fabric.Image(square, {
                originX: 'center', originY: 'center',
                selectable: false, evented: false,
                hasControls: false, hasBorders: false,
                objectCaching: false
            });
            if (userImgObj) canvas.remove(userImgObj);
            userImgObj = img;
            fitPhotoToFrame();
            canvas.add(userImgObj);
            canvas.renderAll();
            btnEdit.disabled = false;
            queueExport();

            try { cropper.destroy(); } catch {}
            cropper = null;
            closeModal();
            } catch (error) { cropUse.disabled = false; status.textContent = 'Could not prepare your photo. Please try another image.'; closeModal(); console.error(error); }
        });

        function queueExport() {
            const revision = ++exportRevision;
            exportFile = null; btnDownload.disabled = true; btnShare.disabled = true;
            preview.hidden = true;
            status.textContent = 'Preparing your PNG…';
            clearTimeout(exportTimer);
            exportTimer = setTimeout(() => {
                try {
                    canvas.discardActiveObject(); canvas.renderAll();
                    // Fixed maximum export dimension avoids huge canvases on desktop or phones.
                    const multiplier = 1600 / Math.max(canvas.getWidth(), canvas.getHeight());
                    const output = canvas.toCanvasElement(multiplier);
                    output.toBlob(blob => {
                        output.width = output.height = 0;
                        if (revision !== exportRevision) return;
                        if (!blob) { status.textContent = 'Could not export the image. Please try again.'; return; }
                        if (exportUrl) URL.revokeObjectURL(exportUrl);
                        exportUrl = URL.createObjectURL(blob);
                        exportFile = new File([blob], fileName, {type:'image/png'});
                        document.getElementById('exportImage').src = exportUrl;
                        document.getElementById('openImage').href = exportUrl;
                        btnDownload.disabled = false; btnShare.disabled = false;
                        status.textContent = 'Ready! Download your PNG or tap Save / share image.';
                    }, 'image/png');
                } catch (error) { status.textContent = 'Could not export the image. Reload the page and try again.'; console.error(error); }
            }, 180);
        }
        btnDownload.addEventListener('click', () => {
            if (!exportUrl || !exportFile) return;
            preview.hidden = false;
            const a = document.createElement('a');
            a.href = exportUrl; a.download = fileName;
            document.body.appendChild(a); a.click();
            setTimeout(() => a.remove(), 1000);
            status.textContent = 'If the download does not appear, use Save / share image or the finished image below.';
        });
        btnShare.addEventListener('click', async () => {
            if (!exportFile) return;
            preview.hidden = false;
            try {
                if (navigator.share && navigator.canShare && navigator.canShare({files:[exportFile]})) {
                    // File is prepared before this tap, preserving the user gesture for iOS.
                    await navigator.share({files:[exportFile]});
                    status.textContent = 'Your image is ready. You can save or share it again.';
                } else {
                    status.textContent = 'Touch and hold the finished image below to save it, or open the full image.';
                    preview.scrollIntoView({behavior:'smooth',block:'center'});
                }
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'Sharing was unavailable. Touch and hold the finished image below, or open the full image.';
            }
        });
    })();
    </script>
</x-app-layout>

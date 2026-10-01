<x-app-layout>
<style>
.es-wrap{max-width:1100px;margin:auto;padding:48px 24px}.es-grid{display:grid;grid-template-columns:1fr 420px;gap:48px;align-items:start}.es-kicker{font-size:12px;letter-spacing:2px;font-weight:800;color:#b94d24}.es-wrap h1{font-size:44px;line-height:1.08;font-weight:800;letter-spacing:-2px;margin:14px 0}.es-muted{color:#62646a;line-height:1.7}.es-tools{margin:28px 0;background:white;border:1px solid #e5e5df;border-radius:22px;padding:24px}.es-tools label{display:block;font-size:14px;font-weight:700;margin-bottom:8px}.es-tools select,.es-tools input{width:100%;border:1px solid #d8d8d3;border-radius:10px;margin-bottom:20px;padding:12px}.es-btn{background:#e65c27;color:white;border:0;border-radius:12px;padding:13px 18px;font-weight:700;cursor:pointer;display:inline-block}.es-secondary{background:white;color:#222;border:1px solid #d6d6d1}.es-actions{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.es-preview{background:#eeeae3;border-radius:28px;padding:20px}.es-preview canvas{width:100%;height:auto;display:block;border-radius:18px;box-shadow:0 12px 28px #0002}.es-status{min-height:24px;color:#286343;font-size:14px}.es-wrap button:disabled{opacity:.55;cursor:wait}@media(max-width:800px){.es-grid{grid-template-columns:1fr}.es-preview{max-width:420px;margin:auto}.es-wrap h1{font-size:36px}}
</style>
<div class="es-wrap">
    <a href="{{ route('events.show', $event) }}" class="text-sm font-semibold">← Back to event</a>
    <div class="es-grid mt-6">
        <div>
            <p class="es-kicker">THE SOCIAL INVITE STUDIO</p>
            <h1>Good plans deserve<br>great company.</h1>
            <p class="es-muted">Turn <strong>{{ $event->name }}</strong> into a social invite. Download a story or square post, then add the event link when you share.</p>
            <div class="es-tools">
                <label for="invite-format">Make it fit</label>
                <select id="invite-format"><option value="story">Story · 1080 × 1920</option><option value="square">Square post · 1080 × 1080</option></select>
                <label for="invite-tone">Choose your style</label>
                <select id="invite-tone"><option value="coral">Sunset · coral & cream</option><option value="night">After hours · midnight & lime</option><option value="lilac">Daydream · lilac & violet</option></select>
                <label for="invite-line">Your invitation</label>
                <input id="invite-line" maxlength="70" value="Who’s coming with me?" autocomplete="off">
                <button id="invite-download" class="es-btn" disabled>Download invite PNG ↓</button>
            </div>
            <div class="es-actions">
                <a id="invite-whatsapp" class="es-btn es-secondary" target="_blank" rel="noopener noreferrer">Share on WhatsApp</a>
                <button id="invite-copy" class="es-btn es-secondary">Copy event link</button>
                <button id="invite-native" class="es-btn es-secondary" hidden>Share invite</button>
            </div>
            <p id="invite-status" class="es-status" role="status" aria-live="polite"></p>
            <p class="es-muted text-sm">A social invite is not a ticket or a booking confirmation. People still need to register. For Instagram stories, upload the image and use the Link sticker with the copied event link.</p>
        </div>
        <div class="es-preview"><canvas id="invite-canvas" width="1080" height="1920" role="img" aria-label="Preview of your social invitation"></canvas></div>
    </div>
</div>
@php
    $shareData = [
        'name' => $event->name,
        'category' => $event->category ?: 'GET TOGETHER',
        'location' => $event->location ?: 'See event page for details',
        'date' => $event->sessions->first() ? \Illuminate\Support\Carbon::parse($event->sessions->first()->session_date)->format('D, d M Y · g:ia') : 'See event page for dates',
        'url' => route('events.show', $event),
        'image' => ($event->banner_url ?: $event->avatar_url) ? asset('storage/'.($event->banner_url ?: $event->avatar_url)) : null,
    ];
@endphp
<script>
(() => {
    const data = {{ \Illuminate\Support\Js::from($shareData) }};
    const canvas = document.getElementById('invite-canvas'), ctx = canvas.getContext('2d');
    const format = document.getElementById('invite-format'), tone = document.getElementById('invite-tone'), line = document.getElementById('invite-line');
    const download = document.getElementById('invite-download'), status = document.getElementById('invite-status');
    const palettes = {coral:['#fff4e9','#e65c27','#29201c','#f2c1a5'],night:['#171e1c','#d9f18a','#ffffff','#35453b'],lilac:['#efe8ff','#7550b5','#292039','#d4b6ed']};
    let poster = null;
    function textLines(value, x, y, width, size, maxLines = 3, weight = 800) {
        ctx.font = `${weight} ${size}px Arial, sans-serif`;
        const words = String(value).split(/\s+/); let current = '', lines = [];
        for (const word of words) {
            const next = current ? current+' '+word : word;
            if (ctx.measureText(next).width > width && current) {lines.push(current);current=word;} else current=next;
        }
        if (current) lines.push(current);
        const clipped = lines.length > maxLines; lines=lines.slice(0,maxLines);
        lines.forEach((text,i) => {
            if (clipped && i===maxLines-1) text+='…';
            while (ctx.measureText(text).width>width && text.length>1) text=text.slice(0,-2)+'…';
            ctx.fillText(text,x,y+i*size*1.18);
        });
        return y+lines.length*size*1.18;
    }
    function render() {
        canvas.height = format.value==='story' ? 1920 : 1080;
        const h=canvas.height, p=palettes[tone.value], story=h>1080;
        ctx.fillStyle=p[0];ctx.fillRect(0,0,1080,h);
        ctx.fillStyle=p[1];ctx.font='800 38px Arial';ctx.fillText('eventib / GOOD PLANS, GREAT COMPANY',70,100);
        const y=story?210:160, photoHeight=story?690:340;
        ctx.fillStyle=p[3];ctx.fillRect(70,y,940,photoHeight);
        if(poster){
            const scale=Math.max(940/poster.width,photoHeight/poster.height);
            ctx.save();ctx.beginPath();ctx.rect(70,y,940,photoHeight);ctx.clip();
            ctx.drawImage(poster,70+(940-poster.width*scale)/2,y+(photoHeight-poster.height*scale)/2,poster.width*scale,poster.height*scale);ctx.restore();
        }else{
            ctx.fillStyle=p[1];ctx.font='800 200px Arial';ctx.fillText('↗',420,y+photoHeight*.65);
        }
        ctx.fillStyle=p[1];textLines(data.category.toUpperCase(),70,y+photoHeight+70,940,26,1);
        ctx.fillStyle=p[2];let bottom=textLines(data.name,70,y+photoHeight+(story?170:130),940,story?78:48,story?3:2);
        bottom=textLines(data.date,70,bottom+35,940,story?32:26,1,600);
        textLines(data.location,70,bottom+22,940,story?32:24,2,400);
        ctx.fillStyle=p[1];ctx.fillRect(70,h-(story?320:200),940,story?160:120);
        ctx.fillStyle=tone.value==='night'?'#171e1c':'#ffffff';
        textLines(line.value || 'Who’s coming with me?',100,h-(story?235:150),880,story?48:38,2);
        ctx.fillStyle=p[2];ctx.font=`500 ${story?27:22}px Arial`;ctx.fillText('Find the details & book your place on Eventib.',70,h-(story?65:40));
        download.disabled=false;
    }
    function shareText(){return `${line.value || 'Who’s coming with me?'}\n${data.name}\n${data.date}\n${data.url}`;}
    function update(){render();document.getElementById('invite-whatsapp').href='https://wa.me/?text='+encodeURIComponent(shareText());}
    [format,tone,line].forEach(el=>el.addEventListener('input',update));
    async function png(){return new Promise((resolve,reject)=>{try{canvas.toBlob(blob=>blob?resolve(blob):reject(new Error('Image export failed.')),'image/png');}catch(error){reject(error);}});}
    download.addEventListener('click',async()=>{
        try{const blob=await png(), url=URL.createObjectURL(blob), a=document.createElement('a');a.href=url;a.download='eventib-social-invite.png';a.click();setTimeout(()=>URL.revokeObjectURL(url),10000);status.textContent='Invite downloaded. Copy the event link to include with your post.';}catch(e){status.textContent='Could not export this image. Try another browser.';}
    });
    document.getElementById('invite-copy').addEventListener('click',async()=>{
        try{await navigator.clipboard.writeText(data.url);status.textContent='Event link copied.';}catch(e){status.textContent='Copy this event link: '+data.url;}
    });
    const native=document.getElementById('invite-native');
    if(navigator.share){native.hidden=false;native.addEventListener('click',async()=>{
        try{const file=new File([await png()],'eventib-social-invite.png',{type:'image/png'});if(navigator.canShare && navigator.canShare({files:[file]})){await navigator.share({files:[file],text:shareText()});}else{await navigator.share({title:data.name,text:shareText(),url:data.url});}}catch(e){if(e.name!=='AbortError')status.textContent='Sharing unavailable here. Download the invite and copy the link.';}
    });}
    update();
    if(data.image){const img=new Image();img.crossOrigin='anonymous';img.onload=()=>{poster=img;update();};img.onerror=()=>{status.textContent='Poster unavailable for export; your invite uses the graphic background.';};img.src=data.image;}
})();
</script>
</x-app-layout>

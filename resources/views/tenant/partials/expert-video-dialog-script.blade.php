{{-- Единый скрипт модалки expert-video-dialog: ленивая подстановка src у <video> и <iframe> embed; при закрытии сброс воспроизведения. --}}
@once('expert-video-dialog-script')
    <script>
        (function () {
            /** Blade {@code e()} кладёт в разметку {@code &amp;}; в части окружений getAttribute отдаёт строку с буквальным {@code &amp;}, и iframe уходит на VK с одним «склеенным» query — «Видеофайл не найден». */
            function unwrapEmbedUrl(raw) {
                if (!raw || typeof raw !== 'string') {
                    return raw;
                }
                return raw
                    .replace(/&amp;/gi, '&')
                    .replace(/&#0*38;/g, '&')
                    .replace(/&#[xX]0*26;/g, '&');
            }
            function playVideoEl(v) {
                try {
                    v.muted = false;
                    var p = v.play();
                    if (p && typeof p.catch === 'function') {
                        p.catch(function () {
                            try {
                                v.muted = true;
                                v.play().catch(function () {});
                            } catch (e2) {}
                        });
                    }
                } catch (e) {}
            }
            function tryOpenEmbed(iframe) {
                if (!iframe) return;
                var es = unwrapEmbedUrl(iframe.getAttribute('data-expert-dialog-embed-src'));
                if (!es) return;
                var cur = iframe.getAttribute('src') || '';
                if (cur === '' || cur === 'about:blank') {
                    iframe.setAttribute('src', es);
                }
            }
            function resetEmbed(iframe) {
                if (!iframe) return;
                try {
                    iframe.setAttribute('src', 'about:blank');
                } catch (e) {}
            }
            function tryPlay(dlg) {
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        var iframe = dlg.querySelector('iframe.expert-video-dialog__embed');
                        if (iframe) {
                            tryOpenEmbed(iframe);
                            return;
                        }
                        var v = dlg.querySelector('video');
                        if (!v) return;
                        var ds = unwrapEmbedUrl(v.getAttribute('data-expert-dialog-src'));
                        if (ds && !v.getAttribute('src')) {
                            v.setAttribute('src', ds);
                            v.removeAttribute('data-expert-dialog-src');
                            v.addEventListener('loadeddata', function onLd() {
                                v.removeEventListener('loadeddata', onLd);
                                playVideoEl(v);
                            });
                            return;
                        }
                        playVideoEl(v);
                    });
                });
            }
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-expert-video-open]');
                if (!btn) return;
                var id = btn.getAttribute('data-expert-video-open');
                if (!id) return;
                var dlg = document.getElementById(id);
                if (dlg && typeof dlg.showModal === 'function') {
                    dlg.showModal();
                    tryPlay(dlg);
                }
            });
            document.addEventListener('click', function (e) {
                var tb = e.target.closest('[data-expert-review-text-open]');
                if (!tb) return;
                var tid = tb.getAttribute('data-expert-review-text-open');
                if (!tid) return;
                var tdlg = document.getElementById(tid);
                if (tdlg && typeof tdlg.showModal === 'function') {
                    tdlg.showModal();
                }
            });
            document.addEventListener('click', function (ev) {
                var t = ev.target;
                if (t && t.tagName === 'DIALOG' && (t.classList.contains('expert-video-dialog'))) {
                    t.close();
                }
            });
            document.addEventListener('close', function (e) {
                var dlg = e.target;
                if (!dlg || dlg.tagName !== 'DIALOG' || !dlg.classList.contains('expert-video-dialog')) return;
                var iframe = dlg.querySelector('iframe.expert-video-dialog__embed');
                if (iframe) {
                    resetEmbed(iframe);
                    return;
                }
                var v = dlg.querySelector('video');
                if (v) { try { v.pause(); v.currentTime = 0; } catch (err) {} }
            }, true);
        })();
    </script>
@endonce

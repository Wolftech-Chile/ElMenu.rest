<?php if ($_SESSION['rol'] === 'admin'): ?>
<section id="seo">
    <h3>Optimización SEO</h3>
    <form method="post" id="form-seo">
        <input type="hidden" name="accion" value="editar_seo">
        
        <fieldset>
            <legend>Información básica SEO</legend>
            <label>Meta descripción (máx 160 caracteres):
                <textarea name="meta_descripcion" maxlength="160" rows="3" style="width:100%;"><?= esc($rest['meta_descripcion']) ?></textarea>
            </label><br>
            
            <label>Palabras clave (separadas por comas):
                <input type="text" name="meta_keywords" value="<?= esc($rest['meta_keywords']) ?>" style="width:100%;">
            </label>
        </fieldset>

        <fieldset>
            <legend>Integración Google</legend>
            <label>ID de Google Analytics:
                <input type="text" name="google_analytics" value="<?= esc($rest['google_analytics']) ?>" placeholder="G-XXXXXXXXXX">
            </label><br>
            
            <label>Meta tag Google Search Console:
                <input type="text" name="google_search_console" value="<?= esc($rest['google_search_console']) ?>" placeholder="<meta name='google-site-verification' ...">
            </label>
        </fieldset>

        <button type="submit" class="btn-seo">Guardar Cambios SEO</button>
    </form>
    <script>
    (function(){
        const formSeo = document.getElementById('form-seo');
        if(!formSeo) return;
        formSeo.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(formSeo);
            ajaxHelpers.sendFormData('includes/api.php', fd).then(function(resp){
                if(resp && resp.ok){
                    NotificationSystem.show('SEO actualizado correctamente','success');
                }else{
                    NotificationSystem.show(resp.error||'Error al guardar SEO','error');
                }
            }).catch(function(){
                NotificationSystem.show('Error de red al guardar SEO','error');
            });
        });
    })();
    </script>

    <div style="margin-top: 20px;">
        <h4>Sitemap XML</h4>
        <p>El sitemap se genera automáticamente cuando hay cambios. También puedes generarlo manualmente:</p>
        <button type="button" id="generar-sitemap" class="btn-seo">Generar sitemap.xml</button>
        <span id="sitemap-status" style="margin-left: 10px;"></span>
    </div>

    <script>
    document.getElementById('generar-sitemap').addEventListener('click', function() {
        this.disabled = true;
        const statusEl = document.getElementById('sitemap-status');
        statusEl.textContent = 'Generando sitemap...';
        fetch('desktop.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'accion=generar_sitemap'
        })
        .then(r => r.json())
        .then(data => {
            statusEl.textContent = data.ok ? '✅ Sitemap generado correctamente' : '❌ Error al generar el sitemap';
            this.disabled = false;
            if (data.ok) {
                setTimeout(() => {
                    statusEl.textContent = '';
                }, 3000);
            }
        })
        .catch(() => {
            statusEl.textContent = '❌ Error al generar el sitemap';
            this.disabled = false;
        });
    });
    </script>
</section>
<?php endif; ?>

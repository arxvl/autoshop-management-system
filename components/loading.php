<?php
// components/loading.php
?>
<style>
    .loading-container { display: none; justify-content: center; align-items: center; padding: 2rem; }
    .spinner { border: 4px solid #f3f3f3; border-top: 4px solid var(--accent); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .loading-active { display: flex; }
</style>
<div id="globalLoader" class="loading-container">
    <div class="spinner"></div>
</div>

<script>
    function showLoader() { document.getElementById('globalLoader').classList.add('loading-active'); }
    function hideLoader() { document.getElementById('globalLoader').classList.remove('loading-active'); }
</script>
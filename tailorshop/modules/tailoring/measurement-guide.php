<div class="measurement-guide" role="group" aria-label="Measurement location reference">
  <div class="measurement-guide-figure">
    <div class="measurement-guide-viewport" data-measurement-viewer
         data-model-src="/tailorshop/public/assets/human_glb.glb">
      <canvas tabindex="0" aria-label="Interactive 3D mannequin. Drag to rotate."></canvas>
      <div class="measurement-guide-loading" role="status">Loading 3D mannequin...</div>
      <div class="measurement-guide-error" role="status" hidden>
        <img src="/tailorshop/assets/img/measurement-guide.svg"
             alt="2D body measurement reference with numbered measurement locations">
        <span>3D model unavailable. Showing the 2D reference.</span>
      </div>
    </div>
    <div class="measurement-guide-hint">Drag to rotate · Scroll or pinch to zoom</div>
    <div class="measurement-guide-caption">Visual reference only — measurements are entered separately</div>
  </div>
  <ul class="measurement-guide-list">
    <li>
      <span class="measurement-guide-number">1</span>
      <span><strong>Bust</strong><small>Around the fullest part of the chest</small></span>
    </li>
    <li>
      <span class="measurement-guide-number">2</span>
      <span><strong>Waist</strong><small>Around the natural waist</small></span>
    </li>
    <li>
      <span class="measurement-guide-number">3</span>
      <span><strong>Hips</strong><small>Around the fullest part of the hips</small></span>
    </li>
    <li>
      <span class="measurement-guide-number">4</span>
      <span><strong>Length</strong><small>From shoulder to the garment's desired hem</small></span>
    </li>
    <li>
      <span class="measurement-guide-number">5</span>
      <span><strong>Shoulder</strong><small>Across the back between shoulder points</small></span>
    </li>
    <li>
      <span class="measurement-guide-number">6</span>
      <span><strong>Sleeve</strong><small>From shoulder point to the desired sleeve end</small></span>
    </li>
  </ul>
</div>
<script type="importmap">
  {
    "imports": {
      "three": "https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js",
      "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/"
    }
  }
</script>
<script type="module" src="/tailorshop/assets/js/measurement-guide.js"></script>

/**
 * 🌱 ECOTIENDA HN — Ferrofluid Background (vanilla WebGL port)
 * Adaptado del componente <Ferrofluid /> de React Bits para uso directo
 * en un sitio PHP tradicional (sin React/build step).
 *
 * Uso:
 *   <div id="ferrofluidBg" class="ferrofluid-bg"></div>
 *   <script src="/assets/js/ferrofluid-bg.js"></script>
 *   <script>
 *     EcoFerrofluid.init('ferrofluidBg', { colors: ['#10B981','#059669','#10B981'] });
 *   </script>
 */
(function (global) {
  'use strict';

  var MAX_COLORS = 8;

  var VERTEX_SRC = [
    'attribute vec2 position;',
    'attribute vec2 uv;',
    'varying vec2 vUv;',
    'void main() {',
    '  vUv = uv;',
    '  gl_Position = vec4(position, 0.0, 1.0);',
    '}'
  ].join('\n');

  var FRAGMENT_SRC = [
    'precision highp float;',
    'uniform vec3  iResolution;',
    'uniform vec2  iMouse;',
    'uniform float iTime;',
    'uniform vec3  uColor0;',
    'uniform vec3  uColor1;',
    'uniform vec3  uColor2;',
    'uniform vec3  uColor3;',
    'uniform vec3  uColor4;',
    'uniform vec3  uColor5;',
    'uniform vec3  uColor6;',
    'uniform vec3  uColor7;',
    'uniform int   uColorCount;',
    'uniform vec2  uFlow;',
    'uniform float uSpeed;',
    'uniform float uScale;',
    'uniform float uTurbulence;',
    'uniform float uFluidity;',
    'uniform float uRimWidth;',
    'uniform float uSharpness;',
    'uniform float uShimmer;',
    'uniform float uGlow;',
    'uniform float uOpacity;',
    'uniform float uMouseEnabled;',
    'uniform float uMouseStrength;',
    'uniform float uMouseRadius;',
    'varying vec2 vUv;',
    '#define PI 3.14159265',
    'vec3 palette(float h) {',
    '  int count = uColorCount;',
    '  if (count < 1) count = 1;',
    '  int idx = int(floor(clamp(h, 0.0, 0.999999) * float(count)));',
    '  if (idx <= 0) return uColor0;',
    '  if (idx == 1) return uColor1;',
    '  if (idx == 2) return uColor2;',
    '  if (idx == 3) return uColor3;',
    '  if (idx == 4) return uColor4;',
    '  if (idx == 5) return uColor5;',
    '  if (idx == 6) return uColor6;',
    '  return uColor7;',
    '}',
    'float hash(vec3 p3) {',
    '  p3 = fract(p3 * 0.1031);',
    '  p3 += dot(p3, p3.zyx + 33.33);',
    '  return fract((p3.x + p3.y) * p3.z);',
    '}',
    'float smin(float a, float b, float k) {',
    '  float r = exp2(-a / k) + exp2(-b / k);',
    '  return -k * log2(r);',
    '}',
    'float sinlerp(float a, float b, float w) {',
    '  return mix(a, b, (sin(w * PI - PI / 2.0) + 1.0) / 2.0);',
    '}',
    'float vn(vec2 p, float s, float seed) {',
    '  vec2 cellp = floor(p / s);',
    '  vec2 relp = mod(p, s);',
    '  float g1 = hash(vec3(cellp, seed));',
    '  float g2 = hash(vec3(cellp.x + 1.0, cellp.y, seed));',
    '  float g3 = hash(vec3(cellp.x + 1.0, cellp.y + 1.0, seed));',
    '  float g4 = hash(vec3(cellp.x, cellp.y + 1.0, seed));',
    '  float bx = sinlerp(g1, g2, relp.x / s);',
    '  float tx = sinlerp(g4, g3, relp.x / s);',
    '  return sinlerp(bx, tx, relp.y / s);',
    '}',
    'float dbn(vec2 p, float s, float seed) {',
    '  float o = s / 2.0;',
    '  float n0 = vn(p, s, seed);',
    '  float n1 = vn(p + vec2(o, o), s, seed + 0.1);',
    '  float n2 = vn(p + vec2(-o, o), s, seed + 0.2);',
    '  float n3 = vn(p + vec2(o, -o), s, seed + 0.3);',
    '  float n4 = vn(p + vec2(-o, -o), s, seed + 0.4);',
    '  return (2.0 * n0 + 1.5 * n1 + 1.25 * n2 + 1.125 * n3 + n4) / 7.0;',
    '}',
    'void mainImage(out vec4 fragColor, in vec2 fragCoord) {',
    '  float ref = 700.0 / max(uScale, 0.05);',
    '  vec2 p = fragCoord / iResolution.y * ref;',
    '  float spd = 200.0 * uSpeed;',
    '  float t = iTime;',
    '  vec2 dir = uFlow;',
    '  vec2 perp = vec2(-dir.y, dir.x);',
    '  float distort1 = vn(p + perp * (t * spd), 60.0, 10.0) * 50.0 * uTurbulence;',
    '  float distort2 = vn(p - perp * (t * spd), 120.0, 15.0) * 100.0 * uTurbulence;',
    '  float peaks = dbn(p + distort1 + dir * (t * spd * 0.5), 40.0, 1.0);',
    '  float peaks2 = dbn(p + distort2 - dir * (t * spd * 0.5), 40.0, 0.0);',
    '  float mapeaks = smin(peaks, peaks2, max(uFluidity, 0.001));',
    '  float mGlow = 0.0;',
    '  if (uMouseEnabled > 0.5) {',
    '    vec2 mp = iMouse / iResolution.y * ref;',
    '    float md = length(p - mp) / ref;',
    '    float rr = max(uMouseRadius, 0.02);',
    '    mGlow = exp(-md * md / (rr * rr)) * uMouseStrength;',
    '  }',
    '  float band = (uRimWidth - abs((mapeaks - 0.4) * 2.0)) * 5.0;',
    '  float ltn = clamp(band - vn(p + dir * (t * spd * 0.5), 60.0, 12.0) * uShimmer, 0.0, 1.0);',
    '  ltn = pow(ltn, uSharpness) * uGlow;',
    '  ltn *= clamp(1.0 - mGlow, 0.0, 1.0);',
    '  float h = clamp(0.5 + (peaks - peaks2) * 0.8, 0.0, 1.0);',
    '  vec3 col = palette(h);',
    '  vec3 outc = col * ltn;',
    '  float a = clamp(max(outc.r, max(outc.g, outc.b)), 0.0, 1.0);',
    '  fragColor = vec4(outc, a * uOpacity);',
    '}',
    'void main() {',
    '  vec4 color;',
    '  mainImage(color, vUv * iResolution.xy);',
    '  gl_FragColor = color;',
    '}'
  ].join('\n');

  function hexToRGB(hex) {
    var c = (hex || '#10b981').replace('#', '');
    while (c.length < 6) c += '0';
    return [
      parseInt(c.slice(0, 2), 16) / 255,
      parseInt(c.slice(2, 4), 16) / 255,
      parseInt(c.slice(4, 6), 16) / 255
    ];
  }

  function prepColors(input) {
    var base = (input && input.length ? input : ['#10B981', '#059669', '#10B981']).slice(0, MAX_COLORS);
    var count = base.length;
    var arr = [];
    for (var i = 0; i < MAX_COLORS; i++) {
      arr.push(hexToRGB(base[Math.min(i, base.length - 1)]));
    }
    return { arr: arr, count: count };
  }

  function flowVec(d) {
    switch (d) {
      case 'up': return [0, 1];
      case 'down': return [0, -1];
      case 'left': return [-1, 0];
      case 'right': return [1, 0];
      default: return [0, -1];
    }
  }

  function compile(gl, type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      console.warn('[Ferrofluid] shader error:', gl.getShaderInfoLog(sh));
      gl.deleteShader(sh);
      return null;
    }
    return sh;
  }

  function shouldRun() {
    if (typeof window === 'undefined') return false;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches && window.innerWidth < 640) return false;
    return true;
  }

  function init(containerId, opts) {
    var container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
    if (!container) return null;
    if (!shouldRun()) return null;

    opts = opts || {};
    var colors = opts.colors || ['#10B981', '#059669', '#10B981'];
    var speed = opts.speed != null ? opts.speed : 0.4;
    var scale = opts.scale != null ? opts.scale : 1.5;
    var turbulence = opts.turbulence != null ? opts.turbulence : 0.8;
    var fluidity = opts.fluidity != null ? opts.fluidity : 0.12;
    var rimWidth = opts.rimWidth != null ? opts.rimWidth : 0.18;
    var sharpness = opts.sharpness != null ? opts.sharpness : 2.6;
    var shimmer = opts.shimmer != null ? opts.shimmer : 1.2;
    var glow = opts.glow != null ? opts.glow : 1.6;
    var flowDirection = opts.flowDirection || 'down';
    var opacity = opts.opacity != null ? opts.opacity : 0.55;
    var mouseInteraction = opts.mouseInteraction !== false;
    var mouseStrength = opts.mouseStrength != null ? opts.mouseStrength : 0.8;
    var mouseRadius = opts.mouseRadius != null ? opts.mouseRadius : 0.32;
    var dampening = opts.mouseDampening != null ? opts.mouseDampening : 0.18;

    var canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;display:block;';
    container.appendChild(canvas);

    var gl = canvas.getContext('webgl', { alpha: true, antialias: true, premultipliedAlpha: false }) ||
             canvas.getContext('experimental-webgl', { alpha: true, antialias: true });
    if (!gl) { container.removeChild(canvas); return null; }

    var vs = compile(gl, gl.VERTEX_SHADER, VERTEX_SRC);
    var fs = compile(gl, gl.FRAGMENT_SHADER, FRAGMENT_SRC);
    if (!vs || !fs) return null;

    var program = gl.createProgram();
    gl.attachShader(program, vs);
    gl.attachShader(program, fs);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
      console.warn('[Ferrofluid] link error:', gl.getProgramInfoLog(program));
      return null;
    }
    gl.useProgram(program);

    // Full-screen triangle
    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
      -1, -1, 0, 0,
       3, -1, 2, 0,
      -1,  3, 0, 2
    ]), gl.STATIC_DRAW);

    var posLoc = gl.getAttribLocation(program, 'position');
    var uvLoc = gl.getAttribLocation(program, 'uv');
    gl.enableVertexAttribArray(posLoc);
    gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 16, 0);
    gl.enableVertexAttribArray(uvLoc);
    gl.vertexAttribPointer(uvLoc, 2, gl.FLOAT, false, 16, 8);

    var U = {};
    ['iResolution', 'iMouse', 'iTime', 'uColor0', 'uColor1', 'uColor2', 'uColor3',
      'uColor4', 'uColor5', 'uColor6', 'uColor7', 'uColorCount', 'uFlow', 'uSpeed',
      'uScale', 'uTurbulence', 'uFluidity', 'uRimWidth', 'uSharpness', 'uShimmer',
      'uGlow', 'uOpacity', 'uMouseEnabled', 'uMouseStrength', 'uMouseRadius'
    ].forEach(function (name) { U[name] = gl.getUniformLocation(program, name); });

    var colorPrep = prepColors(colors);
    for (var i = 0; i < MAX_COLORS; i++) {
      gl.uniform3fv(U['uColor' + i], colorPrep.arr[i]);
    }
    gl.uniform1i(U.uColorCount, colorPrep.count);
    var flow = flowVec(flowDirection);
    gl.uniform2fv(U.uFlow, flow);
    gl.uniform1f(U.uSpeed, speed);
    gl.uniform1f(U.uScale, scale);
    gl.uniform1f(U.uTurbulence, turbulence);
    gl.uniform1f(U.uFluidity, fluidity);
    gl.uniform1f(U.uRimWidth, rimWidth);
    gl.uniform1f(U.uSharpness, sharpness);
    gl.uniform1f(U.uShimmer, shimmer);
    gl.uniform1f(U.uGlow, glow);
    gl.uniform1f(U.uOpacity, opacity);
    gl.uniform1f(U.uMouseEnabled, mouseInteraction ? 1 : 0);
    gl.uniform1f(U.uMouseStrength, mouseStrength);
    gl.uniform1f(U.uMouseRadius, mouseRadius);

    gl.enable(gl.BLEND);
    gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

    var mouseTarget = [0, 0];
    var mouseCurrent = [0, 0];
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    function resize() {
      var rect = container.getBoundingClientRect();
      var w = Math.max(1, Math.round(rect.width * dpr));
      var h = Math.max(1, Math.round(rect.height * dpr));
      if (canvas.width !== w || canvas.height !== h) {
        canvas.width = w;
        canvas.height = h;
        gl.viewport(0, 0, w, h);
        gl.uniform3f(U.iResolution, w, h, 1);
      }
    }
    resize();
    var ro = (typeof ResizeObserver !== 'undefined') ? new ResizeObserver(resize) : null;
    if (ro) ro.observe(container);
    window.addEventListener('resize', resize);

    function onPointerMove(e) {
      var rect = canvas.getBoundingClientRect();
      var x = (e.clientX - rect.left) * dpr;
      var y = (rect.height - (e.clientY - rect.top)) * dpr;
      mouseTarget[0] = x;
      mouseTarget[1] = y;
    }
    if (mouseInteraction) {
      window.addEventListener('pointermove', onPointerMove, { passive: true });
    }

    var raf = null;
    var lastT = 0;
    var running = true;

    document.addEventListener('visibilitychange', function () {
      running = document.visibilityState === 'visible';
      if (running && !raf) raf = requestAnimationFrame(loop);
    });

    function loop(t) {
      raf = requestAnimationFrame(loop);
      if (!running) return;
      gl.uniform1f(U.iTime, t * 0.001);
      if (dampening > 0) {
        if (!lastT) lastT = t;
        var dt = (t - lastT) / 1000;
        lastT = t;
        var factor = Math.min(1, 1 - Math.exp(-dt / Math.max(1e-4, dampening)));
        mouseCurrent[0] += (mouseTarget[0] - mouseCurrent[0]) * factor;
        mouseCurrent[1] += (mouseTarget[1] - mouseCurrent[1]) * factor;
      } else {
        mouseCurrent[0] = mouseTarget[0];
        mouseCurrent[1] = mouseTarget[1];
      }
      gl.uniform2f(U.iMouse, mouseCurrent[0], mouseCurrent[1]);
      gl.clear(gl.COLOR_BUFFER_BIT);
      gl.drawArrays(gl.TRIANGLES, 0, 3);
    }
    raf = requestAnimationFrame(loop);

    return {
      destroy: function () {
        if (raf) cancelAnimationFrame(raf);
        window.removeEventListener('resize', resize);
        if (mouseInteraction) window.removeEventListener('pointermove', onPointerMove);
        if (ro) ro.disconnect();
        if (canvas.parentElement) canvas.parentElement.removeChild(canvas);
      }
    };
  }

  global.EcoFerrofluid = { init: init };
})(typeof window !== 'undefined' ? window : this);

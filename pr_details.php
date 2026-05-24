<?php
session_start();
require_once 'db.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function egp($n) { return 'EGP ' . number_format((float)$n, 2); }

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$product_id) { header('Location: products.php'); exit; }

// Fetch product
$product_result = pg_query_params($conn,
    "SELECT p.*, u.name AS vendor_name
     FROM products p
     LEFT JOIN users u ON u.id = p.vendor_user_id
     WHERE p.id = \$1",
    [$product_id]
);
if (!$product_result || pg_num_rows($product_result) === 0) {
    header('Location: products.php');
    exit;
}
$product = pg_fetch_assoc($product_result);

// Images
$images_result = pg_query_params($conn,
    "SELECT image_url FROM product_images WHERE product_id = \$1 ORDER BY id ASC",
    [$product_id]
);
$images = [];
while ($row = pg_fetch_assoc($images_result)) $images[] = $row['image_url'];

// Specs
$specs = [];
if (!empty($product['specs'])) {
    $decoded = json_decode($product['specs'], true);
    if (is_array($decoded)) $specs = $decoded;
}

// ── Where is the user coming from? ──────────────────────────────────
$referer       = $_SERVER['HTTP_REFERER'] ?? '';
$fromPackages  = strpos($referer, 'packages.php') !== false;

// ── User context ─────────────────────────────────────────────────────
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$userType   = $_SESSION['user_type'] ?? 'guest';
$isLoggedIn = $userId > 0;
$showCart   = !in_array($userType, ['vendor', 'labor', 'company'], true);

// ── User location ────────────────────────────────────────────────────
$userLocation = '';
if ($isLoggedIn) {
    $userRes = pg_query_params($conn,
        "SELECT city, street, country FROM users WHERE id = \$1 LIMIT 1",
        [$userId]);
    if ($userRes && pg_num_rows($userRes) > 0) {
        $uRow  = pg_fetch_assoc($userRes);
        $parts = array_filter([
            trim($uRow['street']  ?? ''),
            trim($uRow['city']    ?? ''),
            trim($uRow['country'] ?? ''),
        ]);
        $userLocation = implode(', ', $parts);
    }
}

// ── Cart state ───────────────────────────────────────────────────────
$inCart  = false;
$cartQty = 0;
if ($showCart && !empty($_SESSION['shop_cart'][(string)$product_id])) {
    $inCart  = true;
    $cartQty = (int)$_SESSION['shop_cart'][(string)$product_id]['qty'];
}

// ── Wizard / Add to Setup context ───────────────────────────────────
$module      = $product['module'] ?? null;
$in_wizard   = isset($_SESSION['wizard']);

// Check if already added in wizard cart
$already_added = false;
if ($in_wizard && $module) {
    $cart_key = $module . '_cart';
    $wizard   = $_SESSION['wizard'];
    if (isset($wizard[$cart_key]['items'])) {
        foreach ($wizard[$cart_key]['items'] as $type => $item) {
            if (isset($item['product_id']) && (int)$item['product_id'] === $product_id) {
                $already_added = true; break;
            }
            foreach (($item['extras'] ?? []) as $extra) {
                if (isset($extra['product_id']) && (int)$extra['product_id'] === $product_id) {
                    $already_added = true; break 2;
                }
            }
        }
    }
}

// ── Handle Add to Setup POST ─────────────────────────────────────────
$add_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_setup') {
    if (!$in_wizard) {
        $add_error = 'No active setup session.';
    } elseif (!$module) {
        $add_error = 'This product has no module assigned.';
    } else {
        $cart_key = $module . '_cart';
        $type     = $product['product_type'] ?? 'general';

        $_SESSION['wizard'][$cart_key]['items'][$type]['extras'][] = [
            'product_id' => (string)$product['id'],
            'name'       => $product['product_name'],
            'brand'      => $product['brand'] ?? null,
            'price'      => (float)$product['price'],
            'unit'       => (float)$product['price'],
            'qty'        => 1,
            'image_url'  => $images[0] ?? null,
            'vendor_name'=> $product['vendor_name'] ?? null,
            'module'     => $module,
            'type'       => $type,
        ];

        header("Location: packages.php?module=" . urlencode($module));
        exit;
    }
}

$outOfStock  = (int)($product['stock_quantity'] ?? 0) <= 0;
$stockCount  = (int)($product['stock_quantity'] ?? 0);
$tier_labels = ['starter' => 'Starter', 'balanced' => 'Balanced', 'premium' => 'Premium'];
$tier_label  = $tier_labels[strtolower($product['tier'] ?? '')] ?? ucfirst($product['tier'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($product['product_name']) ?> — SetupForge</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css?v=20">
    <style>
        * { border-radius: 0 !important; }
        .sf-logo { border-radius: 50% !important; }
        body { background: #f4f6fb; }
        .sf-prd-back { display:inline-flex;align-items:center;gap:6px;color:#004cac;font-size:.875rem;font-weight:600;text-decoration:none;margin-bottom:24px; }
        .sf-prd-back:hover { color:#003a82; }

        /* Gallery */
        .sf-gallery-wrap { background:#fff;border:1.5px solid #e2e8f0;padding:20px; }
        .sf-gallery-main { width:100%;aspect-ratio:1/1;object-fit:contain;cursor:zoom-in;display:block; }
        .sf-gallery-fallback { width:100%;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:4rem;background:#f8fafc; }
        .sf-gallery-thumbs { display:flex;gap:8px;margin-top:12px;flex-wrap:wrap; }
        .sf-gallery-thumb { width:64px;height:64px;object-fit:cover;border:2px solid transparent;cursor:pointer;transition:border-color .15s; }
        .sf-gallery-thumb.active,.sf-gallery-thumb:hover { border-color:#004cac; }

        /* Info panel */
        .sf-prd-panel { background:#fff;border:1.5px solid #e2e8f0;padding:28px; }
        .sf-prd-brand { font-size:.78rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#004cac;margin-bottom:8px; }
        .sf-prd-name { font-size:1.5rem;font-weight:800;color:#111827;line-height:1.3;margin-bottom:12px; }
        .sf-prd-meta-row { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px; }
        .sf-prd-badge { display:inline-block;padding:4px 12px;font-size:.75rem;font-weight:700;background:#eef3fb;color:#004cac; }
        .sf-prd-badge.tier-starter { background:#dcfce7;color:#16a34a; }
        .sf-prd-badge.tier-balanced { background:#fef9c3;color:#b45309; }
        .sf-prd-badge.tier-premium { background:#fce7f3;color:#be185d; }
        .sf-prd-rating { display:flex;align-items:center;gap:6px;margin-bottom:14px; }
        .sf-stars { color:#f59e0b;font-size:1rem; }
        .sf-rating-val { font-size:.88rem;font-weight:700;color:#374151; }

        /* Buy box */
        .sf-buy-box { border:1.5px solid #e2e8f0;padding:20px;background:#fff;margin-top:16px; }
        .sf-buy-box-price { font-size:1.6rem;font-weight:900;color:#111827;margin-bottom:4px; }
        .sf-stock-low  { color:#dc2626;font-size:.88rem;font-weight:700;margin-bottom:12px; }
        .sf-stock-ok   { color:#16a34a;font-size:.88rem;font-weight:700;margin-bottom:12px; }
        .sf-stock-out  { color:#dc2626;font-size:.88rem;font-weight:700;margin-bottom:12px; }

        /* Qty */
        .sf-qty-select { display:flex;align-items:center;gap:12px;margin-bottom:14px; }
        .sf-qty-select label { font-size:.85rem;font-weight:700;color:#374151;white-space:nowrap; }
        .sf-qty-select select { padding:6px 10px;border:1.5px solid #d1d5db;font-size:.88rem;font-weight:600;color:#111827;background:#fff;cursor:pointer;min-width:80px; }

        /* Buttons */
        .sf-prd-btn { display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 16px;border:none;font-size:.92rem;font-weight:700;cursor:pointer;transition:background .15s,color .15s;text-decoration:none;text-align:center;margin-bottom:8px; }
        .sf-prd-btn-primary { background:#004cac;color:#fff; }
        .sf-prd-btn-primary:hover:not(:disabled) { background:#003a82;color:#fff; }
        .sf-prd-btn-primary:disabled { background:#e2e8f0;color:#94a3b8;cursor:default; }
        .sf-prd-btn-buynow { background:var(--sf-secondary);color:#fff;border:none; }
        .sf-prd-btn-buynow:hover { background:#007a76;color:#fff; }
        .sf-prd-btn-success { background:#dcfce7;color:#15803d;border:1.5px solid #16a34a;cursor:default; }
        .sf-prd-btn-setup { background:#004cac;color:#fff;border:none; }
        .sf-prd-btn-setup:hover { background:#003a82;color:#fff; }
        .sf-prd-btn-setup-added { background:#dcfce7;color:#15803d;border:1.5px solid #16a34a;cursor:default; }

        /* Delivery */
        .sf-delivery-box { margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;font-size:.83rem;color:#374151; }
        .sf-delivery-label { font-weight:700;margin-bottom:6px; }
        .sf-delivery-location-wrap { display:flex;align-items:center;gap:8px; }
        .sf-delivery-location-input { flex:1;padding:6px 10px;border:1.5px solid #d1d5db;font-size:.83rem;color:#111827;background:#f8fafc; }
        .sf-delivery-location-input:focus { outline:none;border-color:#004cac;background:#fff; }
        .sf-delivery-save-btn { padding:6px 14px;background:#004cac;color:#fff;border:none;font-size:.8rem;font-weight:700;cursor:pointer; }
        .sf-delivery-save-btn:hover { background:#003a82; }
        .sf-delivery-save-msg { font-size:.75rem;color:#16a34a;margin-top:4px;display:none; }
        .sf-sold-by { margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;font-size:.82rem;color:#6b7280; }
        .sf-sold-by span { font-weight:700;color:#111827; }

        /* Specs */
        .sf-specs-card { background:#fff;border:1.5px solid #e2e8f0;padding:24px 28px;margin-top:24px; }
        .sf-specs-title { font-size:1rem;font-weight:800;color:#111827;margin-bottom:16px;padding-bottom:10px;border-bottom:1.5px solid #f1f5f9; }
        .sf-specs-table { width:100%;border-collapse:collapse; }
        .sf-specs-table tr:not(:last-child) td { border-bottom:1px solid #f1f5f9; }
        .sf-specs-table td { padding:9px 4px;font-size:.875rem;vertical-align:top; }
        .sf-specs-table td:first-child { color:#6b7280;font-weight:700;width:42%;padding-right:16px; }

        /* Lightbox */
        .sf-lightbox { display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center; }
        .sf-lightbox.open { display:flex; }
        .sf-lightbox img { max-width:90vw;max-height:90vh;object-fit:contain; }
        .sf-lightbox-close { position:absolute;top:20px;right:24px;color:#fff;font-size:2rem;cursor:pointer;background:none;border:none; }

        /* Toast */
        #sf-cart-toast { display:none;position:fixed;bottom:24px;right:24px;z-index:9998;background:#111827;color:#fff;padding:14px 20px;font-size:.88rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.18);align-items:center;gap:10px;max-width:320px; }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container py-4" style="max-width:1100px;">
    <a href="javascript:history.back()" class="sf-prd-back"><i class="bi bi-arrow-left"></i> Back</a>

    <div class="row g-4">

        <!-- Gallery -->
        <div class="col-lg-5">
            <div class="sf-gallery-wrap">
                <?php if (!empty($images)): ?>
                    <img src="<?= h($images[0]) ?>" id="sf-main-img" class="sf-gallery-main" alt="<?= h($product['product_name']) ?>">
                    <?php if (count($images) > 1): ?>
                    <div class="sf-gallery-thumbs">
                        <?php foreach ($images as $i => $img): ?>
                            <img src="<?= h($img) ?>" class="sf-gallery-thumb <?= $i===0?'active':'' ?>" data-src="<?= h($img) ?>" alt="">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sf-gallery-fallback"><i class="bi bi-box-seam"></i></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info + Buy Box -->
        <div class="col-lg-7">
            <div class="sf-prd-panel">

                <?php if (!empty($product['brand'])): ?>
                    <div class="sf-prd-brand"><?= h($product['brand']) ?></div>
                <?php endif; ?>

                <div class="sf-prd-name"><?= h($product['product_name']) ?></div>

                <?php if (!empty($product['avg_rating'])): ?>
                <div class="sf-prd-rating">
                    <span class="sf-stars"><?php $rating=(float)$product['avg_rating']; for($s=1;$s<=5;$s++) echo $s<=round($rating)?'★':'☆'; ?></span>
                    <span class="sf-rating-val"><?= number_format($rating,1) ?> / 5</span>
                </div>
                <?php endif; ?>

                <div class="sf-prd-meta-row">
                    <?php if ($tier_label): ?><span class="sf-prd-badge tier-<?= h(strtolower($product['tier']??'')) ?>"><?= h($tier_label) ?></span><?php endif; ?>
                    <?php if (!empty($product['module'])): ?><span class="sf-prd-badge"><?= h($product['module']==='furniture'?'Dining Area':ucfirst($product['module'])) ?></span><?php endif; ?>
                    <?php if (!empty($product['product_type'])): ?><span class="sf-prd-badge"><?= h(ucwords(str_replace('_',' ',$product['product_type']))) ?></span><?php endif; ?>
                </div>

                <div style="border-top:1.5px solid #f1f5f9;margin:16px 0;"></div>

                <!-- Buy Box -->
                <div class="sf-buy-box">
                    <div class="sf-buy-box-price"><?= egp($product['price']) ?></div>

                    <?php if ($outOfStock): ?>
                        <div class="sf-stock-out">Out of Stock</div>
                    <?php elseif ($stockCount <= 5): ?>
                        <div class="sf-stock-low">Only <?= $stockCount ?> left in stock — order soon.</div>
                    <?php else: ?>
                        <div class="sf-stock-ok">In Stock</div>
                    <?php endif; ?>

                    <?php if ($add_error): ?>
                        <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.85rem;"><?= h($add_error) ?></div>
                    <?php endif; ?>

                    <?php if ($fromPackages && $in_wizard && $module): ?>
                        <!-- ── Coming from packages.php: show Add to Setup only ── -->
                        <?php if ($already_added): ?>
                            <button class="sf-prd-btn sf-prd-btn-setup-added" disabled>
                                <i class="bi bi-check2-circle"></i> Already in Setup
                            </button>
                        <?php elseif ($outOfStock): ?>
                            <button class="sf-prd-btn sf-prd-btn-primary" disabled>Out of Stock</button>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_to_setup">
                                <button type="submit" class="sf-prd-btn sf-prd-btn-setup">
                                    <i class="bi bi-plus-circle"></i> Add to Setup
                                </button>
                            </form>
                        <?php endif; ?>

                    <?php elseif ($showCart): ?>
                        <!-- ── Regular cart flow ── -->
                        <?php if (!$outOfStock): ?>
                            <div class="sf-qty-select">
                                <label for="qty-select">Quantity:</label>
                                <select id="qty-select">
                                    <?php for($q=1;$q<=min(10,$stockCount);$q++): ?>
                                        <option value="<?= $q ?>"><?= $q ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <?php if ($inCart): ?>
                                <a href="cart.php" class="sf-prd-btn sf-prd-btn-success">
                                    <i class="bi bi-check2-circle"></i> In Cart (<?= $cartQty ?>) — View Cart
                                </a>
                                <button class="sf-prd-btn sf-prd-btn-primary" id="add-to-cart-btn" data-product-id="<?= $product_id ?>">
                                    <i class="bi bi-cart-plus"></i> Add More to Cart
                                </button>
                            <?php else: ?>
                                <button class="sf-prd-btn sf-prd-btn-primary" id="add-to-cart-btn" data-product-id="<?= $product_id ?>">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            <?php endif; ?>

                            <button class="sf-prd-btn sf-prd-btn-buynow" id="buy-now-btn" data-product-id="<?= $product_id ?>">
                                Buy Now
                            </button>

                        <?php else: ?>
                            <button class="sf-prd-btn sf-prd-btn-primary" disabled>Out of Stock</button>
                        <?php endif; ?>

                    <?php elseif (!$isLoggedIn): ?>
                        <a href="auth/login.php?next=<?= h('pr_details.php?id='.$product_id) ?>" class="sf-prd-btn sf-prd-btn-primary">
                            Sign in to Buy
                        </a>
                    <?php endif; ?>

                    <!-- Delivery location -->
                    <div class="sf-delivery-box">
                        <div class="sf-delivery-label"><i class="bi bi-geo-alt me-1" style="color:#004cac"></i>Deliver to</div>
                        <div class="sf-delivery-location-wrap">
                            <input type="text" class="sf-delivery-location-input" id="delivery-location-input"
                                   placeholder="Add your delivery location"
                                   value="<?= h($userLocation) ?>"
                                   <?= !$isLoggedIn ? 'readonly' : '' ?>>
                            <?php if ($isLoggedIn): ?>
                                <button class="sf-delivery-save-btn" id="save-location-btn">Save</button>
                            <?php endif; ?>
                        </div>
                        <div class="sf-delivery-save-msg" id="location-save-msg"><i class="bi bi-check2 me-1"></i>Location saved</div>
                        <?php if (!$isLoggedIn): ?>
                            <div style="margin-top:4px;font-size:.75rem;color:#9ca3af;">
                                <a href="auth/login.php?next=<?= h('pr_details.php?id='.$product_id) ?>" style="color:#004cac">Sign in</a> to set your delivery location
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($product['vendor_name'])): ?>
                    <div class="sf-sold-by">Sold by <span><?= h($product['vendor_name']) ?></span></div>
                    <?php endif; ?>

                </div><!-- /.sf-buy-box -->
            </div><!-- /.sf-prd-panel -->
        </div>
    </div>

    <!-- Specs -->
    <?php if (!empty($specs)): ?>
    <div class="sf-specs-card">
        <div class="sf-specs-title">Specifications</div>
        <table class="sf-specs-table">
            <?php foreach ($specs as $key => $val): ?>
            <tr>
                <td><?= h(ucwords(str_replace('_',' ',$key))) ?></td>
                <td><?= h(is_array($val)?implode(', ',$val):$val) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

</div>

<!-- Lightbox -->
<div class="sf-lightbox" id="sf-lightbox">
    <button class="sf-lightbox-close" id="sf-lb-close">&times;</button>
    <img src="" id="sf-lb-img" alt="">
</div>

<!-- Toast -->
<div id="sf-cart-toast">
    <i class="bi bi-cart-check-fill" style="color:#22c55e;font-size:1.1rem;"></i>
    <span id="sf-cart-toast-msg">Added to cart</span>
    <a href="cart.php" style="color:#60a5fa;font-weight:700;margin-left:4px;text-decoration:none;">View Cart</a>
</div>

<!-- Buy Now form -->
<form id="buy-now-form" method="POST" action="shop_checkout.php" style="display:none;">
    <input type="hidden" name="buy_now" value="1">
    <input type="hidden" name="buy_now_product_id" id="buy-now-product-id" value="<?= $product_id ?>">
    <input type="hidden" name="buy_now_qty" id="buy-now-qty" value="1">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    // Gallery
    const mainImg = document.getElementById('sf-main-img');
    document.querySelectorAll('.sf-gallery-thumb').forEach(function(t){
        t.addEventListener('click',function(){
            if(mainImg) mainImg.src=this.dataset.src;
            document.querySelectorAll('.sf-gallery-thumb').forEach(x=>x.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Lightbox
    const lightbox=document.getElementById('sf-lightbox');
    const lbImg=document.getElementById('sf-lb-img');
    if(mainImg&&lightbox) mainImg.addEventListener('click',function(){lbImg.src=this.src;lightbox.classList.add('open');});
    const lbClose=document.getElementById('sf-lb-close');
    if(lbClose) lbClose.addEventListener('click',()=>lightbox.classList.remove('open'));
    if(lightbox) lightbox.addEventListener('click',e=>{if(e.target===lightbox)lightbox.classList.remove('open');});

    // Toast
    function showToast(msg){
        const toast=document.getElementById('sf-cart-toast');
        document.getElementById('sf-cart-toast-msg').textContent=msg;
        toast.style.display='flex';
        clearTimeout(toast._timer);
        toast._timer=setTimeout(()=>{toast.style.display='none';},3000);
    }

    function updateBadge(count){
        let badge=document.querySelector('.sf-navbar-cart-badge');
        const cartLink=document.querySelector('.sf-navbar-cart');
        if(!cartLink) return;
        if(count>0){
            if(!badge){badge=document.createElement('span');badge.className='sf-navbar-cart-badge';cartLink.appendChild(badge);}
            badge.textContent=count;
        } else { if(badge) badge.remove(); }
    }

    function getQty(){ const s=document.getElementById('qty-select'); return s?parseInt(s.value)||1:1; }

    // Add to Cart
    const addBtn=document.getElementById('add-to-cart-btn');
    if(addBtn){
        addBtn.addEventListener('click',function(){
            const pid=this.dataset.productId;
            const qty=getQty();
            addBtn.disabled=true;
            addBtn.innerHTML='<i class="bi bi-hourglass-split"></i> Adding...';
            const fd=new FormData();
            fd.append('product_id',pid);
            fd.append('qty',qty);
            fetch('shop_cart_add.php',{method:'POST',body:fd})
                .then(r=>r.json())
                .then(data=>{
                    if(data.ok){
                        addBtn.innerHTML='<i class="bi bi-check2-circle"></i> Added to Cart';
                        addBtn.classList.remove('sf-prd-btn-primary');
                        addBtn.classList.add('sf-prd-btn-success');
                        addBtn.style.cursor='default';
                        updateBadge(data.cart_count);
                        showToast(data.product_name+' added to cart');
                    } else {
                        addBtn.disabled=false;
                        addBtn.innerHTML='<i class="bi bi-cart-plus"></i> Add to Cart';
                        alert(data.error||'Could not add to cart.');
                    }
                })
                .catch(()=>{addBtn.disabled=false;addBtn.innerHTML='<i class="bi bi-cart-plus"></i> Add to Cart';});
        });
    }

    // Buy Now
    const buyNowBtn=document.getElementById('buy-now-btn');
    if(buyNowBtn){
        buyNowBtn.addEventListener('click',function(){
            document.getElementById('buy-now-qty').value=getQty();
            document.getElementById('buy-now-form').submit();
        });
    }

    // Save location
    const saveLocBtn=document.getElementById('save-location-btn');
    if(saveLocBtn){
        saveLocBtn.addEventListener('click',function(){
            const loc=document.getElementById('delivery-location-input').value.trim();
            const msg=document.getElementById('location-save-msg');
            const fd=new FormData();
            fd.append('location',loc);
            fetch('save_user_location.php',{method:'POST',body:fd})
                .then(r=>r.json())
                .then(data=>{if(data.ok){msg.style.display='block';setTimeout(()=>{msg.style.display='none';},2000);}});
        });
    }
})();
</script>
</body>
</html>
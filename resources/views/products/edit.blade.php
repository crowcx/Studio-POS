<!DOCTYPE html>
<html>
<head>
    <title>Edit Produk</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; cursor: pointer; border-radius: 4px; color: white; }
        .btn-save { background-color: #007bff; }
        .btn-back { background-color: #6c757d; text-decoration: none; display: inline-block; }
        .info-box { background-color: #e8f4fd; border-left: 4px solid #007bff; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .warning-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Produk: {{ $product->name }}</h2>
        
        @if($errors->any())
            <div style="color: red; margin-bottom: 15px;">
                <ul>@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
            </div>
        @endif

        @if($product->category && in_array($product->category->name, ['Jasa cetak', 'Jasa foto, edit, dan pemasangan']))
        <div class="info-box">
            <strong>⚠️ Produk Jasa</strong><br>
            Produk ini termasuk kategori <strong>{{ $product->category->name }}</strong> yang memiliki stok tak terbatas.
            Stok tidak akan berkurang saat transaksi di kasir.
        </div>
        @endif

        <form action="{{ route('products.update', $product->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="form-group">
                <label>Nama Produk *</label>
                <input type="text" name="name" value="{{ $product->name }}" required {{ Auth::user()->role === 'staff gudang' ? 'readonly style="background-color: #f0f0f0;"' : '' }}>
            </div>
            <div class="form-group">
                <label>Kategori *</label>
                <select name="category_id" required {{ Auth::user()->role === 'staff gudang' ? 'disabled style="background-color: #f0f0f0;"' : '' }}>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @if(Auth::user()->role === 'staff gudang')
                    <input type="hidden" name="category_id" value="{{ $product->category_id }}">
                @endif
            </div>
            <div class="form-group">
                <label>Tipe/Warna (Opsional)</label>
                <input type="text" name="type_color" value="{{ $product->type_color }}" placeholder="Contoh: Merah, 4R, Gold" {{ Auth::user()->role === 'staff gudang' ? 'readonly style="background-color: #f0f0f0;"' : '' }}>
            </div>
            <div class="form-group">
                <label>Barcode (Opsional)</label>
                <input type="text" name="barcode" value="{{ $product->barcode }}" placeholder="Kode barcode" {{ Auth::user()->role === 'staff gudang' ? 'readonly style="background-color: #f0f0f0;"' : '' }}>
            </div>
            
            <h3>Manajemen Stok</h3>
            <div class="form-group">
                <label>Stok Saat Ini</label>
                <input type="number" name="current_stock" value="{{ $product->stock }}" readonly style="background-color: #f0f0f0;">
                <small style="color: #666;">Stok saat ini: {{ $product->stock }}</small>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                <div class="form-group">
                    <label>Stok Tambahan</label>
                    <input type="number" name="stock_addition" value="0" min="0" placeholder="Masukkan jumlah tambahan">
                    <small style="color: #666;">Tambahkan stok baru</small>
                </div>
                <div class="form-group">
                    <label>Stok Pengurangan</label>
                    @if($product->category && in_array($product->category->name, ['Jasa cetak', 'Jasa foto, edit, dan pemasangan']))
                    <input type="number" name="stock_reduction" value="0" min="0" max="0" placeholder="Tidak berlaku untuk produk jasa" readonly style="background-color: #f0f0f0;">
                    <small style="color: #666;">Produk jasa tidak bisa dikurangi stoknya</small>
                    @else
                    <input type="number" name="stock_reduction" value="0" min="0" max="{{ $product->stock }}" placeholder="Masukkan jumlah pengurangan">
                    <small style="color: #666;">Kurangi stok (maks: {{ $product->stock }})</small>
                    @endif
                </div>
            </div>
            
            <h3>Harga</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                @if(Auth::user()->role === 'admin')
                <div class="form-group">
                    <label>Harga Beli (Modal) *</label>
                    <input type="number" name="buy_price" value="{{ $product->buy_price }}" step="0.01" min="0" required>
                </div>
                @endif
                <div class="form-group">
                    <label>Harga Umum *</label>
                    <input type="number" name="price_general" value="{{ $product->price_general }}" step="0.01" min="0" required {{ Auth::user()->role === 'staff gudang' ? 'readonly style="background-color: #f0f0f0;"' : '' }}>
                </div>
                <div class="form-group">
                    <label>Harga Reseller *</label>
                    <input type="number" name="price_agent1" value="{{ $product->price_agent1 }}" step="0.01" min="0" required {{ Auth::user()->role === 'staff gudang' ? 'readonly style="background-color: #f0f0f0;"' : '' }}>
                </div>
                <div class="form-group">
                    <label>Harga Grosir *</label>
                    <input type="number" name="price_agent2" value="{{ $product->price_agent2 }}" step="0.01" min="0" required {{ Auth::user()->role === 'staff gudang' ? 'readonly style="background-color: #f0f0f0;"' : '' }}>
                </div>
            </div>

            <br>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-save">Simpan Perubahan</button>
                <a href="{{ route('products.index') }}" class="btn btn-back">Batal</a>
            </div>
        </form>
    </div>
</body>
</html>

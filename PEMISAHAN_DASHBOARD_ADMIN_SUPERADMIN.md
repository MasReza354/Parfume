# Pemisahan Dashboard Admin dan Super Admin

## Tanggal: 30 November 2025

### Perubahan Besar

Dashboard yang sebelumnya digabung untuk role `admin` dan `superadmin` sekarang **dipisah menjadi 2 dashboard terpisah**:

1. **Super Admin Dashboard** - `/superadmin/dashboard.php`
2. **Admin Dashboard** - `/admin/dashboard.php`

---

## Struktur Folder Baru

```
Parfume-Web/
├── superadmin/
│   ├── dashboard.php
│   ├── superadmin.css
│   ├── superadmin.js
│   └── upload_product_image.php
├── admin/
│   ├── dashboard.php
│   ├── admin.css
│   ├── admin.js
│   └── upload_product_image.php
└── partnership/
    ├── dashboard.php
    ├── partnership.css
    └── partnership.js
```

---

## Perbedaan Fitur

### Super Admin Dashboard (`/superadmin/dashboard.php`)
**Akses Penuh ke Semua Fitur:**

✅ **Dashboard** - Statistik lengkap
✅ **Produk** - Tambah, edit, hapus, toggle status
✅ **Pesanan** - Lihat dan update status
✅ **Aroma** - Tambah, edit, hapus
✅ **Tipe** - Tambah, edit, hapus
✅ **Pengguna** - Tambah, edit, hapus user (termasuk admin dan partnership)
✅ **Toko Cabang** - Tambah, edit, hapus toko cabang

**Permission:**
- Bisa hapus produk, aroma, tipe, toko cabang
- Bisa mengelola semua user
- Akses penuh tanpa batasan

---

### Admin Dashboard (`/admin/dashboard.php`)
**Akses Terbatas:**

✅ **Dashboard** - Statistik lengkap
✅ **Produk** - Edit (nama, harga, deskripsi, gambar, stok), tidak bisa hapus
✅ **Pesanan** - Lihat dan update status
✅ **Aroma** - Tambah dan edit, tidak bisa hapus
✅ **Tipe** - Tambah dan edit, tidak bisa hapus
✅ **Toko Cabang** - Tambah dan edit, tidak bisa hapus
❌ **Pengguna** - Tidak ada akses

**Permission:**
- Tidak bisa hapus produk, aroma, tipe, toko cabang
- Tidak bisa mengelola user
- Tidak bisa edit type dan scent produk (hanya superadmin)

---

### Partnership Dashboard (`/partnership/dashboard.php`)
**Akses Sangat Terbatas:**

✅ **Dashboard** - Statistik
✅ **Produk** - View only (tidak bisa edit/hapus/toggle status)
✅ **Aroma** - View only
✅ **Tipe** - View only
✅ **Toko Cabang** - Hanya bisa edit toko miliknya sendiri (tidak termasuk Toko Pusat)

**Permission:**
- Hanya bisa melihat produk, aroma, tipe
- Hanya bisa edit toko cabang miliknya
- Tidak bisa akses pesanan dan pengguna

---

## Redirect Login

### Sebelum:
```php
if (in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    header('Location: ../admin/dashboard.php');
}
```

### Sesudah:
```php
if ($_SESSION['user_role'] === 'superadmin') {
    header('Location: ../superadmin/dashboard.php');
} elseif ($_SESSION['user_role'] === 'admin') {
    header('Location: ../admin/dashboard.php');
} elseif ($_SESSION['user_role'] === 'partnership') {
    header('Location: ../partnership/dashboard.php');
}
```

---

## Permission Check

### Super Admin Dashboard:
```php
// Check if user is superadmin only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
  header('Location: ../index.php');
  exit;
}
```

### Admin Dashboard:
```php
// Check if user is admin only (not superadmin)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}
```

### Partnership Dashboard:
```php
// Allow access for admin, superadmin, and partnership
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin', 'partnership'])) {
    header('Location: ../index.php');
    exit;
}
```

---

## File yang Dimodifikasi

### 1. Folder Baru:
- ✅ `superadmin/` - Copy dari folder `admin/`
- ✅ `superadmin/dashboard.php` - Dashboard khusus superadmin
- ✅ `superadmin/superadmin.css` - Rename dari admin.css
- ✅ `superadmin/superadmin.js` - Rename dari admin.js

### 2. Folder Admin (Dimodifikasi):
- ✅ `admin/dashboard.php` - Dashboard khusus admin (permission terbatas)
- ✅ `admin/admin.css` - Tetap sama
- ✅ `admin/admin.js` - Tetap sama

### 3. File Lain:
- ✅ `auth/login.php` - Update redirect berdasarkan role
- ✅ `config/database.php` - Fungsi `isAdmin()` tetap sama (untuk partnership)

---

## Testing

### Test Super Admin:
1. Login dengan akun superadmin
2. Redirect ke `/superadmin/dashboard.php`
3. Pastikan semua menu muncul (termasuk Pengguna)
4. Pastikan bisa hapus produk, aroma, tipe, toko cabang
5. Pastikan bisa mengelola user

### Test Admin:
1. Login dengan akun admin
2. Redirect ke `/admin/dashboard.php`
3. Pastikan menu Pengguna tidak muncul
4. Pastikan tidak ada button hapus di produk, aroma, tipe, toko cabang
5. Pastikan bisa tambah/edit aroma dan tipe
6. Pastikan bisa edit produk (kecuali type dan scent)

### Test Partnership:
1. Login dengan akun partnership
2. Redirect ke `/partnership/dashboard.php`
3. Pastikan produk, aroma, tipe read-only
4. Pastikan hanya bisa edit toko cabang miliknya

---

## Keuntungan Pemisahan

### 1. **Keamanan Lebih Baik**
- Setiap role memiliki dashboard terpisah
- Permission check lebih ketat
- Tidak ada kondisi if-else yang kompleks

### 2. **Maintenance Lebih Mudah**
- Kode lebih bersih dan terorganisir
- Mudah menambah/mengurangi fitur per role
- Tidak ada konflik permission

### 3. **User Experience Lebih Baik**
- Admin tidak melihat fitur yang tidak bisa diakses
- Interface lebih sederhana sesuai kebutuhan
- Tidak ada button yang disabled/hidden

### 4. **Scalability**
- Mudah menambah role baru
- Mudah customize dashboard per role
- Tidak ada dependency antar role

---

## Catatan Penting

1. **Fungsi `isAdmin()`** di `config/database.php` masih mengizinkan admin, superadmin, dan partnership karena digunakan untuk partnership dashboard.

2. **Partnership masih bisa akses admin dashboard** jika mereka tahu URL-nya, tapi akan di-redirect karena permission check.

3. **File CSS dan JS** di setiap folder independent, jadi perubahan di satu dashboard tidak mempengaruhi yang lain.

4. **Upload product image** masih menggunakan file yang sama di setiap folder.

---

## Status: ✅ SELESAI

Semua dashboard sudah dipisah dan berfungsi dengan baik!

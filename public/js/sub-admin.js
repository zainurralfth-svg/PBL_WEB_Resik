// ========================================
// SUB ADMIN DESA - JAVASCRIPT
// ========================================

let currentMode = 'create';
let kecamatans = [];

// ===== MODAL FUNCTIONS =====
function openSubAdminModal(mode, data = null) {
    console.log('🔍 Mode:', mode);
    console.log('🔍 Data:', data);

    currentMode = mode;

    // ✅ AMBIL ELEMENT DULU
    const modal = document.getElementById('subAdminModal');
    const modalTitle = document.getElementById('subAdminModalTitle');
    const form = document.getElementById('subAdminForm');
    const passwordGroup = document.getElementById('passwordGroup');
    const passwordHint = document.getElementById('passwordHint');
    const btnSubmit = document.getElementById('btnSubmit');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    // ✅ DEBUG: CEK APAKAH ELEMENT ADA
    console.log('✅ modalTitle:', modalTitle);
    console.log('✅ form:', form);

    if (!modalTitle) {
        console.error('❌ ERROR: modalTitle TIDAK DITEMUKAN!');
        alert('ERROR: Element modalTitle tidak ditemukan! Cek console.');
        return;
    }

    // ✅ UPDATE JUDUL DULU (SEBELUM form.reset)
    if (mode === 'create') {
        console.log('✅ MASUK BLOK CREATE');
        modalTitle.textContent = 'Tambah Sub Admin Desa';
        console.log('✅ Judul di-set ke:', modalTitle.textContent);
    } else {
        console.log('✅ MASUK BLOK EDIT');
        modalTitle.textContent = 'Edit Sub Admin Desa';
        console.log('✅ Judul di-set ke:', modalTitle.textContent);
    }

    // ✅ BARU RESET FORM
    if (form) {
        form.reset();
    }
    clearErrors();

    // Reset password type
    if (passwordInput) {
        passwordInput.type = 'password';
        passwordInput.value = '';
    }
    if (eyeIcon) eyeIcon.className = 'fas fa-eye';

    // ✅ CLEAR MANUAL SEMUA INPUT
    document.getElementById('nama').value = '';
    document.getElementById('email').value = '';
    document.getElementById('no_telepon').value = '';
    document.getElementById('id_kecamatan').value = '';
    document.getElementById('id_desa').innerHTML = '<option value="">-- Pilih Desa --</option>';
    document.getElementById('id_desa').disabled = true;

    // ✅ KONFIGURASI BERDASARKAN MODE
    if (mode === 'create') {
        form.action = form.dataset.storeUrl;
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('formId').value = '';
        passwordGroup.style.display = 'block';
        passwordHint.textContent = '';
        passwordInput.required = true;
        btnSubmit.textContent = 'Simpan';
    } else {
        form.action = form.dataset.updateUrl + '/' + data.id_admin;
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('formId').value = data.id_admin;
        passwordGroup.style.display = 'block';
        passwordHint.textContent = '(Kosongkan jika tidak ingin mengubah)';
        passwordInput.required = false;
        btnSubmit.innerHTML = '<i class="fas fa-sync-alt"></i> Update';

        // Fill form
        document.getElementById('nama').value = data.nama || '';
        document.getElementById('email').value = data.email || '';
        document.getElementById('no_telepon').value = data.no_telepon || '';
        document.getElementById('id_kecamatan').value = data.id_kecamatan || '';

        // Load desa based on kecamatan
        if (data.id_kecamatan) {
            loadDesa(data.id_kecamatan).then(() => {
                document.getElementById('id_desa').value = data.id_desa || '';
            });
        }
    }

    // ✅ PASTIKAN JUDUL TETAP BENAR (double check)
    setTimeout(() => {
        if (mode === 'create') {
            modalTitle.textContent = 'Tambah Sub Admin Desa';
        } else {
            modalTitle.textContent = 'Edit Sub Admin Desa';
        }
        console.log('✅ FINAL Judul:', modalTitle.textContent);
    }, 50);

    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);

    // Disable body scroll
    document.body.style.overflow = 'hidden';
}

function closeSubAdminModal() {
    const modal = document.getElementById('subAdminModal');
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = ''; // Enable body scroll
    }, 300);

    // Reset form
    const form = document.getElementById('subAdminForm');
    if (form) {
        form.reset();
        clearErrors();
    }
}

// ===== LOAD DESA BY KECAMATAN =====
function loadDesa(kecamatanId) {
    const desaSelect = document.getElementById('id_desa');

    if (!kecamatanId) {
        desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
        desaSelect.disabled = true;
        return Promise.resolve();
    }

    desaSelect.disabled = true;
    desaSelect.innerHTML = '<option value="">Loading...</option>';

    return fetch(`/admin/sub-admin/desa/${kecamatanId}`)
        .then(response => response.json())
        .then(data => {
            desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
            data.forEach(desa => {
                const option = document.createElement('option');
                option.value = desa.id_desa;
                option.textContent = desa.nama_desa;
                desaSelect.appendChild(option);
            });
            desaSelect.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            desaSelect.innerHTML = '<option value="">Error loading desa</option>';
            desaSelect.disabled = false;
        });
}

// ===== CLEAR ERRORS =====
function clearErrors() {
    document.querySelectorAll('[id$="Error"]').forEach(el => {
        el.style.display = 'none';
        el.textContent = '';
    });
    document.querySelectorAll('#subAdminForm .form-control').forEach(el => {
        el.style.borderColor = '';
    });
}

// ===== SIDEBAR TOGGLE =====
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}

document.addEventListener('click', function (event) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');

    if (window.innerWidth <= 768) {
        // ✅ CHECK NULL SEBELUM contains()
        if (sidebar && toggleBtn &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
});

window.addEventListener('resize', function () {
    const sidebar = document.querySelector('.sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
    }
});

// ===== FORM SUBMIT =====
document.addEventListener('DOMContentLoaded', function () {
    const subAdminForm = document.getElementById('subAdminForm');

    if (subAdminForm) {
        subAdminForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors();

            const formData = new FormData(this);
            const url = this.action;
            const btnSubmit = document.getElementById('btnSubmit');

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        throw new Error('Server returned non-JSON response');
                    }
                })
                .then(data => {
                    if (data.success) {
                        closeSubAdminModal();
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        if (data.errors) {
                            Object.keys(data.errors).forEach(key => {
                                const errorEl = document.getElementById(key + 'Error');
                                const inputEl = document.getElementById(key);
                                if (errorEl) {
                                    errorEl.textContent = data.errors[key][0];
                                    errorEl.style.display = 'block';
                                }
                                if (inputEl) {
                                    inputEl.style.borderColor = '#dc3545';
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message || 'Terjadi kesalahan'
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan sistem: ' + error.message
                    });
                })
                .finally(() => {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = currentMode === 'create' ?
                        '<i class="fas fa-save"></i> Simpan' :
                        '<i class="fas fa-sync-alt"></i> Update';
                });
        });
    }
});

// ===== CONFIRM DELETE =====
function confirmDelete(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Sub Admin Desa akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('deleteForm');
            form.action = form.dataset.deleteUrl + '/' + id;
            form.submit();
        }
    });
}

// ===== MODAL EVENT LISTENERS =====
document.addEventListener('DOMContentLoaded', function () {
    const subAdminModal = document.getElementById('subAdminModal');

    if (subAdminModal) {
        // ✅ HAPUS: Close modal on outside click (tidak digunakan lagi)
        // Hanya close via tombol X
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSubAdminModal();  // ← GANTI INI
        }
    });

    // ✅ PASSWORD SHOW/HIDE TOGGLE
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    if (togglePassword && passwordInput && eyeIcon) {
        togglePassword.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                eyeIcon.className = 'fas fa-eye';
            }
        });
    }
});

// ===== SESSION FLASH MESSAGES =====
document.addEventListener('DOMContentLoaded', function () {
    const successMessage = document.querySelector('meta[name="session-success"]');
    if (successMessage) {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: successMessage.content,
            timer: 3000,
            showConfirmButton: false
        });
    }

    const errorMessage = document.querySelector('meta[name="session-error"]');
    if (errorMessage) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: errorMessage.content,
            timer: 3000,
            showConfirmButton: false
        });
    }
});

// ===== FILTER KECAMATAN & DESA =====
document.addEventListener('DOMContentLoaded', function () {
    const filterKecamatan = document.getElementById('filterKecamatanSubAdmin');
    const filterDesa = document.getElementById('filterDesaSubAdmin');

    if (filterKecamatan) {
        filterKecamatan.addEventListener('change', function () {
            const kecamatanId = this.value;

            if (!kecamatanId) {
                if (filterDesa) {
                    filterDesa.innerHTML = '<option value="">Semua Desa</option>';
                    filterDesa.disabled = true;
                    filterDesa.style.opacity = '0.6';
                }
                return;
            }

            fetch(`/admin/sub-admin/desa/${kecamatanId}`)
                .then(response => response.json())
                .then(data => {
                    if (filterDesa) {
                        filterDesa.innerHTML = '<option value="">Semua Desa</option>';
                        data.forEach(desa => {
                            const option = document.createElement('option');
                            option.value = desa.id_desa;
                            option.textContent = desa.nama_desa;
                            filterDesa.appendChild(option);
                        });
                        filterDesa.disabled = false;
                        filterDesa.style.opacity = '1';
                    }
                })
                .catch(error => {
                    console.error('Error loading desa:', error);
                    if (filterDesa) {
                        filterDesa.innerHTML = '<option value="">Error loading desa</option>';
                    }
                });
        });
    }

    const kecamatanId = filterKecamatan?.value;
    const btnReset = document.getElementById('btnResetSubAdmin');
    if (kecamatanId && btnReset) {
        btnReset.style.display = 'inline-flex';
    }
});

// ===== APPLY FILTER =====
function applyFilterSubAdmin() {
    const kecamatanId = document.getElementById('filterKecamatanSubAdmin')?.value;
    const desaId = document.getElementById('filterDesaSubAdmin')?.value;

    const url = new URL(window.location.href);

    if (kecamatanId) {
        url.searchParams.set('kecamatan_id', kecamatanId);
    } else {
        url.searchParams.delete('kecamatan_id');
    }

    if (desaId) {
        url.searchParams.set('desa_id', desaId);
    } else {
        url.searchParams.delete('desa_id');
    }

    const btnReset = document.getElementById('btnResetSubAdmin');
    if (btnReset) {
        btnReset.style.display = 'inline-flex';
    }

    window.location.href = url.toString();
}

// ===== RESET FILTER (HANYA 1 FUNGSI) =====
function resetFilterSubAdmin() {
    const url = new URL(window.location.href);
    url.searchParams.delete('kecamatan_id');
    url.searchParams.delete('desa_id');

    const btnReset = document.getElementById('btnResetSubAdmin');
    if (btnReset) {
        btnReset.style.display = 'none';
    }

    const filterKecamatan = document.getElementById('filterKecamatanSubAdmin');
    const filterDesa = document.getElementById('filterDesaSubAdmin');

    if (filterKecamatan) {
        filterKecamatan.value = '';
    }

    if (filterDesa) {
        filterDesa.innerHTML = '<option value="">Semua Desa</option>';
        filterDesa.disabled = true;
        filterDesa.style.opacity = '0.6';
    }

    // ✅ Clear search juga
    const searchInput = document.getElementById('searchSubAdmin');
    const clearBtn = document.getElementById('clearSearchSubAdmin');
    if (searchInput) searchInput.value = '';
    if (clearBtn) clearBtn.style.display = 'none';

    window.location.href = url.toString();
}

// ========================================
// LIVE SEARCH CLIENT-SIDE (TANPA AJAX)
// ========================================

/**
 * Filter tabel Sub Admin berdasarkan keyword
 */
function filterSubAdmin() {
    const input = document.getElementById('searchSubAdmin');
    const clearBtn = document.getElementById('clearSearchSubAdmin');
    const filter = input?.value.toLowerCase().trim() || '';

    if (clearBtn) {
        clearBtn.style.display = filter.length > 0 ? 'block' : 'none';
    }

    // ===== FILTER DESKTOP TABLE =====
    const desktopTable = document.querySelector('.desktop-table .data-table');
    if (desktopTable) {
        const tbody = desktopTable.querySelector('tbody');
        const rows = tbody?.querySelectorAll('tr') || [];
        let visibleCount = 0;

        const existingEmpty = tbody.querySelector('.empty-search');
        if (existingEmpty) existingEmpty.remove();

        rows.forEach(row => {
            if (row.querySelector('.empty-state') || row.querySelector('td[colspan]')) {
                row.style.display = '';
                return;
            }

            const nama = row.cells[1]?.textContent?.toLowerCase() || '';
            const email = row.cells[2]?.textContent?.toLowerCase() || '';
            const telepon = row.cells[3]?.textContent?.toLowerCase() || '';
            const wilayah = row.cells[4]?.textContent?.toLowerCase() || '';

            const allText = `${nama} ${email} ${telepon} ${wilayah}`;

            if (allText.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount === 0 && filter.length > 0) {
            const tr = document.createElement('tr');
            tr.className = 'empty-search';
            tr.innerHTML = `
                <td colspan="7" style="text-align: center; padding: 40px 20px; color: #666;">
                    <p style="margin: 0; font-size: 16px;">🔍 Tidak ada hasil ditemukan</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px;">Coba kata kunci lain</p>
                </td>
            `;
            tbody.appendChild(tr);
        }
    }

    // ===== FILTER MOBILE CARDS =====
    const mobileCards = document.querySelectorAll('.mobile-cards .petugas-card');
    let mobileVisibleCount = 0;

    const existingEmptyMobile = document.querySelector('.mobile-cards .empty-search');
    if (existingEmptyMobile) existingEmptyMobile.remove();

    mobileCards.forEach(card => {
        const nama = card.querySelector('.card-row:nth-child(1) .card-value')?.textContent?.toLowerCase() || '';
        const email = card.querySelector('.card-row:nth-child(2) .card-value')?.textContent?.toLowerCase() || '';
        const telepon = card.querySelector('.card-row:nth-child(3) .card-value')?.textContent?.toLowerCase() || '';
        const wilayah = card.querySelector('.card-row:nth-child(4) .badge-wilayah-mobile')?.textContent?.toLowerCase() || '';

        const allText = `${nama} ${email} ${telepon} ${wilayah}`;

        if (allText.includes(filter)) {
            card.style.display = '';
            mobileVisibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    if (mobileVisibleCount === 0 && filter.length > 0) {
        const mobileContainer = document.querySelector('.mobile-cards');
        if (mobileContainer) {
            const div = document.createElement('div');
            div.className = 'empty-search';
            div.style.cssText = 'text-align: center; padding: 40px 20px; background: #f9f9f9; border-radius: 8px; color: #666;';
            div.innerHTML = `
                <p style="margin: 0; font-size: 16px;">🔍 Tidak ada hasil ditemukan</p>
                <p style="margin: 5px 0 0 0; font-size: 14px;">Coba kata kunci lain</p>
            `;
            mobileContainer.appendChild(div);
        }
    }
}

/**
 * Clear search input
 */
function clearSearchSubAdmin() {
    const input = document.getElementById('searchSubAdmin');
    const clearBtn = document.getElementById('clearSearchSubAdmin');

    if (input) {
        input.value = '';
        input.focus();
    }
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }

    filterSubAdmin();
}

// ========================================
// HELPER FUNCTION UNTUK TOMBOL EDIT
// ========================================

/**
 * Helper function untuk tombol Edit
 * Dipanggil dari Blade dengan ID admin
 */
function openModalEdit(idAdmin) {
    console.log('🔧 openModalEdit called with ID:', idAdmin);

    // Ambil data dari variabel global yang sudah di-set di Blade
    const adminData = window.subAdminsData || [];
    const admin = adminData.find(a => a.id_admin == idAdmin);

    if (admin) {
        console.log('✅ Admin data found:', admin);
        openSubAdminModal('edit', admin);  // ← GANTI INI
    } else {
        console.error('❌ Admin data not found for ID:', idAdmin);
        alert('Error: Data admin tidak ditemukan!');
    }
}

console.log('✅ Sub Admin JS loaded - Live search ready!');
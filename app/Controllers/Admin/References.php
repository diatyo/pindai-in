<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ItemReferenceModel;

class References extends BaseController
{
    public function index()
    {
        $type = $this->request->getGet('type') === 'location' ? 'location' : 'category';
        $q = trim((string) $this->request->getGet('q'));
        $status = (string) $this->request->getGet('status');
        $references = model(ItemReferenceModel::class);
        $references->where('type', $type);
        if ($q !== '') {
            $references->like('name', $q);
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $references->where('is_active', $status === 'active' ? 1 : 0);
        }
        $rows = $references->orderBy('name_key')->paginate(per_page(10));
        $pager = $references->pager;
        $editing = null;
        $editId = (int) $this->request->getGet('edit');
        if ($editId) {
            $editing = $references->find($editId);
            if (! $editing || $editing->type !== $type) {
                return redirect()->to('/admin/references?type=' . $type)->with('error', 'Referensi tidak ditemukan.');
            }
        }
        $counts = \Config\Database::connect()->table('item_references')->where('type', $type)
            ->select('COUNT(*) AS total, SUM(is_active) AS active', false)->get()->getRow();
        return view('admin/references', [
            'title' => 'Data Referensi', 'type' => $type, 'q' => $q, 'status' => $status,
            'references' => $rows, 'pager' => $pager, 'editing' => $editing,
            'usage' => $references->usageCounts($type),
            'total' => (int) $counts->total, 'active' => (int) $counts->active,
        ]);
    }

    public function save()
    {
        $type = (string) $this->request->getPost('type');
        $id = (int) $this->request->getPost('id');
        $target = '/admin/references?type=' . ($type === 'location' ? 'location' : 'category');
        $rules = [
            'id' => ['label' => 'ID referensi', 'rules' => 'permit_empty|is_natural_no_zero'],
            'type' => ['label' => 'Jenis', 'rules' => 'required|in_list[category,location]'],
            'name' => ['label' => 'Nama', 'rules' => 'required|max_length[' . ($type === 'category' ? 60 : 100) . ']'],
        ];
        if (! $this->validate($rules)) {
            return redirect()->to($target . ($id ? '&edit=' . $id : ''))->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            model(ItemReferenceModel::class)->saveEntry($id ?: null, $type, (string) $this->request->getPost('name'));
        } catch (\DomainException $e) {
            return redirect()->to($target . ($id ? '&edit=' . $id : ''))->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Gagal menyimpan referensi: {message}', ['message' => $e->getMessage()]);
            return redirect()->to($target . ($id ? '&edit=' . $id : ''))->withInput()->with('error', 'Referensi gagal disimpan. Nama mungkin baru saja digunakan; muat ulang dan coba lagi.');
        }
        return redirect()->to($target)->with('success', $id ? 'Referensi dan nama pada barang terkait berhasil diperbarui.' : 'Referensi ditambahkan dan dapat dipilih pada form barang.');
    }

    public function toggle(int $id)
    {
        return $this->change($id, false);
    }

    public function delete(int $id)
    {
        return $this->change($id, true);
    }

    private function change(int $id, bool $delete)
    {
        $references = model(ItemReferenceModel::class);
        $row = $references->find($id);
        $target = '/admin/references?type=' . ($row->type ?? 'category');
        try {
            if ($delete) {
                $references->deleteUnused($id);
                $message = 'Referensi yang belum digunakan berhasil dihapus.';
            } else {
                $updated = $references->toggleEntry($id);
                $message = $updated->is_active ? 'Referensi diaktifkan kembali.' : 'Referensi dinonaktifkan. Data pada barang lama tetap tersimpan.';
            }
        } catch (\DomainException $e) {
            return redirect()->to($target)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Gagal mengubah referensi {id}: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            return redirect()->to($target)->with('error', 'Referensi gagal diperbarui. Silakan coba lagi.');
        }
        return redirect()->to($target)->with('success', $message);
    }
}

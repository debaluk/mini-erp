
        if ($purchase->status === 'posted') {
            return redirect()->route('inventori.pembelian.show', $id)
                ->with('swal_error', 'Faktur POSTED tidak dapat diedit.');
        }

        $dueDate = $request->payment_method === 'credit'
            ? ($purchase->due_date ?: now()->addDays(30)->toDateString())
            : null;

        DB::table('purchases')->where('id', $id)->update([
            'purchase_date' => $request->purchase_date,
            'supplier_invoice_no' => $request->supplier_invoice_no,
            'supplier_invoice_date' => $request->purchase_date,
            'payment_method' => $request->payment_method,
            'due_date' => $dueDate,
            'memo' => $request->memo,
            'updated_at' => now(),
        ]);

        return redirect()->route('inventori.pembelian.show', $id)
            ->with('swal_success', 'Draft Faktur Pembelian berhasil diperbarui.');
    }

    /**
     * Action Post Faktur Pembelian
     */
    public function post($id)
    {
        return $this->executePosting($id);
    }

    private function executePosting($id, $warehouseId = null)
    {
        try {
            $purchaseNo = DB::transaction(function () use ($id, $warehouseId) {
                $purchase = DB::table('purchases')
                    ->whereNull('deleted_at')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$purchase) {
                    throw new \RuntimeException('Faktur tidak ditemukan.');
                }

                if ($purchase->status === 'posted') {
                    throw new \RuntimeException('Faktur sudah POSTED.');
                }

                if ($purchase->source_type !== 'po' && (int) $purchase->goods_received === 1) {
                    if (!$warehouseId) {
                        throw new \RuntimeException('Gudang wajib dipilih untuk penerimaan barang langsung.');
                    }

                    $items = DB::table('purchase_items')
                        ->where('purchase_id', $purchase->id)
                        ->get();

                    $receiptRequest = Request::create('/inventori/penerimaan', 'POST', [
                        'purchase_id' => $purchase->id,
                        'warehouse_id' => $warehouseId,
                        'receipt_date' => $purchase->purchase_date,
                        'memo' => 'Penerimaan langsung dari Faktur '.$purchase->purchase_no,
                        'items' => $items->map(fn ($item) => [
                            'purchase_item_id' => $item->id,
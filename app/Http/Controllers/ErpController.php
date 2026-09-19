    public function supplierMaster(Request $request)
    {
        $entity = $this->entityId();

        if ($request->has('draw')) {
            $query = DB::table('suppliers')->where('entity_id', $entity);
            $search = trim((string) $request->input('search.value', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    foreach (['code','name','category','phone','whatsapp','email','website','address','country'] as $column) {
                        $q->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            }

            $total = DB::table('suppliers')->where('entity_id', $entity)->count();
            $filtered = $query->count();
            $length = max(1, (int) $request->input('length', 15));
            $rows = $query->orderByDesc('id')
                ->offset(max(0, (int) $request->input('start', 0)))
                ->limit($length)
                ->get(['id','code','name','category','phone','whatsapp','email','website','address','country','is_active']);

            return response()->json([
                'draw'=>(int)$request->input('draw'),
                'recordsTotal'=>$total,
                'recordsFiltered'=>$filtered,
                'data'=>$rows,
            ]);
        }

        return view('erp.master-supplier');
    }

    public function supplierStore(Request $request)
    {
        $entity = $this->entityId();
        $data = $request->validate([
            'name'=>['required','string','max:255'],
            'category'=>['nullable','string','max:100'],
            'phone'=>['nullable','string','max:100'],
            'whatsapp'=>['nullable','string','max:100'],
            'email'=>['nullable','email','max:255'],
            'website'=>['nullable','string','max:255'],
            'address'=>['nullable','string'],
            'country'=>['nullable','string','max:100'],
            'is_active'=>['required','boolean'],
        ]);

        $lastNumber = DB::table('suppliers')->where('entity_id',$entity)->where('code','like','SUP-%')->get(['code'])
            ->map(fn($row)=>preg_match('/^SUP-(\d+)$/',$row->code,$m)?(int)$m[1]:0)->max() ?? 0;
        do {
            $lastNumber++;
            $code='SUP-'.str_pad((string)$lastNumber,5,'0',STR_PAD_LEFT);
        } while(DB::table('suppliers')->where('entity_id',$entity)->where('code',$code)->exists());

        DB::table('suppliers')->insert([
            'entity_id'=>$entity,'code'=>$code,'name'=>trim($data['name']),
            'category'=>isset($data['category'])?trim($data['category']):null,
            'phone'=>isset($data['phone'])?trim($data['phone']):null,
            'whatsapp'=>isset($data['whatsapp'])?trim($data['whatsapp']):null,
            'email'=>isset($data['email'])?trim($data['email']):null,
            'website'=>isset($data['website'])?trim($data['website']):null,
            'address'=>isset($data['address'])?trim($data['address']):null,
            'country'=>isset($data['country'])?trim($data['country']):null,
            'credit_limit'=>0,'is_active'=>(int)$data['is_active'],'created_at'=>now(),'updated_at'=>now(),
        ]);

        return response()->json(['message'=>'Supplier berhasil disimpan.']);
    }

    public function supplierUpdate(Request $request, int $id)
    {
        $entity=$this->entityId();
        abort_unless(DB::table('suppliers')->where('entity_id',$entity)->where('id',$id)->exists(),404,'Supplier tidak ditemukan.');
        $data=$request->validate([
            'name'=>['required','string','max:255'],'category'=>['nullable','string','max:100'],
            'phone'=>['nullable','string','max:100'],'whatsapp'=>['nullable','string','max:100'],
            'email'=>['nullable','email','max:255'],'website'=>['nullable','string','max:255'],
            'address'=>['nullable','string'],'country'=>['nullable','string','max:100'],'is_active'=>['required','boolean'],
        ]);
        DB::table('suppliers')->where('entity_id',$entity)->where('id',$id)->update([
            'name'=>trim($data['name']),'category'=>isset($data['category'])?trim($data['category']):null,
            'phone'=>isset($data['phone'])?trim($data['phone']):null,'whatsapp'=>isset($data['whatsapp'])?trim($data['whatsapp']):null,
            'email'=>isset($data['email'])?trim($data['email']):null,'website'=>isset($data['website'])?trim($data['website']):null,
            'address'=>isset($data['address'])?trim($data['address']):null,'country'=>isset($data['country'])?trim($data['country']):null,
            'is_active'=>(int)$data['is_active'],'updated_at'=>now(),
        ]);
        return response()->json(['message'=>'Supplier berhasil diperbarui.']);
    }

    public function supplierDelete(Request $request, int $id)
    {
        $entity=$this->entityId();
        abort_unless(DB::table('suppliers')->where('entity_id',$entity)->where('id',$id)->exists(),404,'Supplier tidak ditemukan.');
        try { DB::table('suppliers')->where('entity_id',$entity)->where('id',$id)->delete(); }
        catch (\Throwable $e) { return response()->json(['message'=>'Supplier sudah digunakan dalam transaksi dan tidak dapat dihapus. Nonaktifkan supplier jika masih diperlukan untuk histori.'],422); }
        return response()->json(['message'=>'Supplier berhasil dihapus.']);
    }

    public function master(Request $request, string $type)    {
        $config = $this->masterConfig($type);
        $entity = $this->entityId();

        if ($request->ajax() && $request->has('draw')) {
            $columns = $config['columns'];
            $query = DB::table($config['table'])->where('entity_id', $entity);

            $search = trim((string) $request->input('search.value', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($columns, $search) {
                    foreach ($columns as $column) {
                        $q->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
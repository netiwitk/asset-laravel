<div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgb(0 0 0 / 0.08);">
    <p style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">บัญชีทดลอง</p>
    <p style="font-size: 0.8rem; opacity: 0.7; margin-bottom: 0.75rem;">กดเพื่อเข้าใช้งานทันที ข้อมูลเป็นตัวอย่างทั้งหมด</p>
    <div style="display: grid; gap: 0.5rem;">
        <x-filament::button tag="a" :href="route('demo.login', 'officer')" icon="heroicon-o-clipboard-document-check">
            เจ้าหน้าที่พัสดุ — อนุมัติ ส่งมอบ ส่งซ่อม
        </x-filament::button>
        <x-filament::button tag="a" :href="route('demo.login', 'staff')" color="gray" outlined icon="heroicon-o-user">
            พนักงาน — ขอยืมทรัพย์สิน
        </x-filament::button>
        <x-filament::button tag="a" :href="route('demo.login', 'admin')" color="gray" outlined icon="heroicon-o-cog-6-tooth">
            ผู้ดูแลระบบ — ตั้งค่าหน่วยงาน หมวดหมู่
        </x-filament::button>
    </div>
</div>

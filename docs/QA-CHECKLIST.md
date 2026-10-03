# QA Checklist — ระบบบริหารทรัพย์สิน

Checklist มาตรฐานสำหรับเว็บแอป ปรับให้เข้ากับระบบนี้ (Laravel 13 + Filament 5 แบบ server-rendered ด้วย Livewire ไม่มี REST API แยก)

**สัญลักษณ์**

- ✅ มีอยู่แล้ว (ระบุว่าทำด้วยอะไร)
- 🛠 เพิ่มหรือแก้ในรอบ QA นี้
- ➖ ไม่เกี่ยวกับระบบนี้ (ระบุเหตุผล)
- ⚠️ ข้อจำกัดที่รู้อยู่

**ทดสอบล่าสุด**

- `php artisan test`: 36 tests
- `node tests/Browser/smoke.mjs`: 14 ขั้นตอน ทดสอบกับเครื่อง dev, Docker image (production) และเว็บจริงหลัง deploy

---

## 1. Loading / State

- ✅ **Page loading**: เปิด SPA mode แล้ว ตอนเปลี่ยนหน้ามีแถบความคืบหน้าด้านบน
- ✅ **Button loading หลัง submit**: ปุ่มของ Filament แสดง spinner ระหว่างรอ
- ✅ **Table/List loading**: ตารางแสดงสถานะโหลดตอนค้นหา กรอง และเปลี่ยนหน้า
- ✅ **Skeleton loading**: widget บน dashboard โหลดแบบ lazy และมี placeholder ระหว่างรอ
- ✅ **Disable ปุ่มขณะกำลัง submit**: ใช้ `wire:loading.attr="disabled"` ทุกปุ่ม action
- ✅ **ป้องกัน double click / double submit**:
  - ฝั่ง UI ปิดปุ่มระหว่างรอ
  - ฝั่งเซิร์ฟเวอร์ใช้ compare-and-set ทั้งกับทรัพย์สินและคำขอยืม
  - database มี partial unique index กันการอนุมัติซ้ำ
- 🛠 **Empty state**: มีข้อความภาษาไทยเฉพาะของแต่ละตาราง (ทรัพย์สิน การยืม งานซ่อม ความเคลื่อนไหว)
- ✅🛠 **Error state**:
  - เมื่อ request ล้มเหลว Filament แจ้งเตือนเอง (error notifications)
  - มีหน้า error ภาษาไทยครบ 403, 404, 419, 429, 500 และ 503
- ✅ **Success state**: ทุก action แจ้งผลด้วย toast
- 🛠 **No result (ค้นหาแล้วไม่พบ)**: empty state แนะนำให้เปลี่ยนคำค้นหา ล้างตัวกรอง หรือเลือกแท็บ "ทั้งหมด"

## 2. Dialog / Popup

- ✅ **Modal / popup**: ใช้ modal ของ Filament
- ✅ **Confirm ก่อน action สำคัญ**: อนุมัติ, ไม่อนุมัติ, ส่งมอบ, ยกเลิกคำขอ และจำหน่าย
- ✅ **Delete confirmation**: การลบเดี่ยวและลบหลายรายการต้องยืนยันก่อน
- ➖ **Logout confirmation**: การออกจากระบบไม่ทำให้ข้อมูลเสียหาย จึงไม่ต้องยืนยัน
- ✅ **Warning dialog**: การจำหน่ายแจ้งว่า "ย้อนกลับไม่ได้ ประวัติจะยังอยู่ในระบบ"
- ➖ **Success / error dialog**: ใช้ toast แทน เพื่อไม่ขัดจังหวะการทำงาน
- ✅ **Modal ปิดด้วยปุ่ม X**
- ✅ **Modal ปิดด้วย ESC**: มีขั้นตอนทดสอบใน smoke test
- ✅ **Modal ปิดเมื่อกดพื้นหลัง**: เป็นค่าเริ่มต้นของ Filament

## 3. Notification / Feedback

- ✅ **Success toast**: ทุก action
- ✅ **Error toast**: ถ้าผิดกฎธุรกิจจะแสดงข้อความไทย เช่น "ทรัพย์สินนี้มีคำขอยืมที่อนุมัติแล้ว ต้องยกเลิกคำขอก่อน"
- ➖ **Warning / info toast**: ยังไม่มีกรณีที่ต้องใช้
- ✅🛠 **ข้อความอ่านเข้าใจง่าย**:
  - ข้อความ error ของระบบเขียนเป็นภาษาไทยทั้งหมด
  - เพิ่ม `lang/th/validation.php` ข้อความ validation จึงไม่เป็นภาษาอังกฤษปนแล้ว
- ✅ **แจ้งผลหลัง create / update / delete**: ใช้ notification ของ Filament (ภาษาไทย)
- 🛠 **แจ้ง network error**:
  - มีแถบแจ้งเมื่อขาดการเชื่อมต่อ (`wire:offline`)
  - request ที่ล้มเหลวมี notification ของ Filament แจ้ง

## 4. Form

- ✅ **Required field**: มีเครื่องหมาย * และตรวจซ้ำที่เซิร์ฟเวอร์
- ✅ **Validation**: ตรวจที่เซิร์ฟเวอร์ทุกครั้ง และมี constraint ใน database เป็นชั้นสุดท้าย
- ✅ **Error message ใต้ช่อง**
- ✅ **Email validation**: ฟอร์มผู้ใช้
- ✅ **Password validation**: อย่างน้อย 8 ตัวอักษร
- 🛠 **Confirm password**: เพิ่มช่องยืนยันรหัสผ่าน
- ✅🛠 **Min / max length**: เลขครุภัณฑ์, ชื่อ, serial, ที่ตั้ง และร้านซ่อม
- 🛠 **Number min / max**: ราคาและค่าซ่อมต้องอยู่ระหว่าง 0–999,999,999,999.99 ซึ่งพอดีกับ `numeric(14,2)`
- ✅ **Date validation**:
  - วันที่ได้มาต้องไม่เกินวันนี้
  - กำหนดคืนและวันที่คาดว่าจะได้คืนต้องไม่ก่อนวันนี้
- 🛠 **Trim ช่องว่าง**: Livewire ข้าม middleware `TrimStrings` จึงตัดช่องว่างใน `TextInput` และ `Textarea` ทุกช่องแทน ยกเว้นช่องรหัสผ่าน
- ➖ **Disable submit ถ้าข้อมูลไม่ครบ**: เลือกตรวจตอนกดบันทึก แล้วแสดง error ใต้ช่องและเลื่อนไปช่องแรกที่ผิด เพราะผู้ใช้รู้ว่าต้องแก้อะไร ต่างจากปุ่มที่กดไม่ได้โดยไม่บอกเหตุผล
- ✅ **Loading ตอน submit**
- ✅ **ป้องกัน submit ซ้ำ**
- ✅ **Reset form**: มีปุ่ม "บันทึกและเพิ่มอีกรายการ" และ "ยกเลิก"
- 🛠 **Autofocus**: ช่องเลขครุภัณฑ์ในฟอร์มเพิ่มทรัพย์สิน ส่วน modal ของ Filament ย้าย focus เข้าไปใน modal ให้เอง
- ✅ **Placeholder / label ชัดเจน**: ทุกช่องมี label ภาษาไทย และใส่ placeholder ตัวอย่างในช่องที่ควรมี
- ✅ **แสดง required \***
- 🛠 **Unsaved changes warning**: เปิด `unsavedChangesAlerts()` แล้ว

## 5. CRUD

- ✅ **ทรัพย์สิน**:
  - ทำได้ครบทั้งเพิ่ม ดู ดูรายละเอียด และแก้ไข
  - การลบเป็น soft delete ทำได้เฉพาะผู้ดูแลระบบ และเฉพาะชิ้นที่ว่างอยู่
- ✅ **คำขอยืม**:
  - สร้างและดูได้ แล้วเปลี่ยนสถานะตามขั้นตอน
  - ไม่มีแก้ไขหรือลบโดยเจตนา เพราะเป็นเอกสาร ถ้าไม่ใช้แล้วให้กด "ยกเลิก"
- ✅ **ผู้ใช้ / หน่วยงาน / หมวดหมู่**: เพิ่ม ดู และแก้ไขได้ ไม่มีการลบ ให้ปิดใช้งานแทน เพราะประวัติยังอ้างถึงอยู่
- ➖ **ประวัติการเคลื่อนไหว**: เป็น append-only มี trigger ใน database ห้ามแก้และห้ามลบ
- ✅ **Confirm ก่อน delete**
- ✅ **Loading**
- ✅ **Success message**
- ✅ **Error message**
- ✅ **Refresh ข้อมูลหลัง action**: Livewire render ใหม่ และตัวเลขบนแท็บกับเมนูอัปเดตตาม
- ✅ **Permission ของแต่ละ action**: กำหนดด้วย `AssetPolicy`, `UserPolicy` และ `visible()` ตามบทบาท มี test ครอบคลุม

## 6. Table / List

- ✅ **Pagination**: หน้าละ 10 รายการ เปลี่ยนจำนวนต่อหน้าได้
- ✅ **Search**
- ✅ **Filter**: มีแท็บกรองด่วน และตัวกรองหน่วยงานกับหมวดหมู่
- ✅ **Sort**
- ✅ **จำนวนรายการ**: มีตัวเลขบนแท็บ และข้อความ "แสดง x ถึง y จาก z รายการ"
- ✅ **Empty state**
- ✅ **Loading state**
- ✅ **Error state**
- ✅ **Reset filter**
- ✅ **Row action / View / Edit / Delete**: ปุ่มเป็นไอคอนที่มี tooltip และ `aria-label`
- ✅ **Responsive บนมือถือ**: ซ่อนคอลัมน์รอง ให้เห็นชื่อ สถานะ และเมนู ⋮ ได้โดยไม่ต้องเลื่อน
- 🛠 **Long text handling**:
  - ชื่อทรัพย์สินตัดขึ้นบรรทัดใหม่ (`wrap`)
  - วัตถุประสงค์และผลการซ่อมตัดด้วย `limit`
- ✅ **Horizontal scroll ถ้าคอลัมน์เยอะ**: ตารางเลื่อนในกรอบของตัวเอง หน้าเว็บทั้งหน้าไม่เลื่อนตาม

## 7. Authentication

- ✅ **Login**: จำกัดการพยายาม 5 ครั้ง
- ✅ **Logout**
- ➖ **Register**: เป็นระบบภายในองค์กร ผู้ดูแลระบบเป็นคนสร้างบัญชี
- ⚠️ **Forgot / reset password**: ยังไม่ได้ต่อระบบอีเมล ตอนนี้ผู้ดูแลตั้งรหัสใหม่ให้ในหน้าผู้ใช้ เมื่อมีอีเมลแล้วเปิดได้ด้วย `->passwordReset()`
- ✅🛠 **Session expired**: Livewire แจ้งให้โหลดหน้าใหม่ และมีหน้า 419 ภาษาไทย
- ➖ **Token expired / refresh token**: ใช้ session cookie ไม่ได้ใช้ token
- ✅ **Redirect หลัง login**: กลับไปหน้าที่ตั้งใจจะเปิด
- ✅ **401**: redirect ไปหน้า login
- ✅🛠 **403**: มีหน้าภาษาไทย และมี test ครอบคลุม
- ✅ **Protected routes**
- ✅ **Role / permission**

## 8. Navigation

- ✅ **Navbar / sidebar / active menu / breadcrumb**
- ➖ **ปุ่ม back**: ใช้ breadcrumb ร่วมกับปุ่ม back ของ browser
- ✅ **Browser back / forward ทำงานถูก**: มีขั้นตอนทดสอบใน smoke test
- ✅ **Logo → home**
- ✅ **Page title**: แต่ละหน้ามี title ของตัวเอง เช่น "ทะเบียนทรัพย์สิน"
- 🛠 **404 page**

## 9. Error handling

แอปนี้ server-render ทั้งหมดด้วย Livewire ไม่มี REST API แยก ด้านล่างนี้จึงจับคู่รหัส HTTP กับพฤติกรรมของระบบนี้

- ✅ **Loading / success / error**
- ➖ **400**
- ✅ **401**
- ✅🛠 **403**
- ✅🛠 **404**
- ✅ **409 conflict**: กรณีสองคนแก้รายการเดียวกันพร้อมกัน ระบบตอบว่า "ถูกเปลี่ยนโดยผู้ใช้อื่นแล้ว กรุณาโหลดหน้าใหม่"
- ✅ **422**: แสดง error ใต้ช่อง
- ✅🛠 **429**:
  - จำกัดความถี่ของการ login และลิงก์ demo (`throttle:20,1`)
  - มีหน้า 429 ภาษาไทย
- 🛠 **500**
- 🛠 **Timeout / network offline**: มีแถบแจ้งเมื่อขาดการเชื่อมต่อ และ Filament แจ้งเมื่อ request ล้มเหลว
- ✅ **Retry**: กดทำซ้ำได้ และหน้า 419 มีปุ่ม "โหลดหน้าใหม่"
- ✅ **ไม่โชว์ technical message**: production ตั้ง `APP_DEBUG=false` และใช้หน้า error ภาษาไทย

## 10. Responsive

- ✅ **Desktop / laptop / tablet / mobile**: ทดสอบที่ 1440px และ 390–500px
- ✅ **Navbar mobile**: มีปุ่ม ☰
- ✅ **Modal บนมือถือ**
- ✅ **Table บนมือถือ**
- ✅ **Form บนมือถือ**: จาก 2 คอลัมน์ยุบเหลือ 1 คอลัมน์
- ✅ **Font ไม่เล็กเกินไป**: เนื้อหา 14px ขึ้นไป label 12px ขึ้นไป
- ✅ **ปุ่มกดง่ายบน touch screen**: พื้นที่กดอย่างน้อย 44px
- ✅ **ไม่มี horizontal scroll ที่ไม่จำเป็น**

## 11. UX

- ✅ **Cursor pointer / hover / disabled / active**
- ✅ **Focus**: แก้ focus ring ที่หายไปแล้วในรอบตรวจ design
- ✅ **Tooltip**: ปุ่มไอคอน
- 🛠 **Copy to clipboard**: กดเลขครุภัณฑ์ในตารางเพื่อคัดลอกได้ ในหน้ารายละเอียดมีอยู่แล้ว
- ✅ **Password show / hide**
- ➖ **Character counter**: ไม่มีช่องข้อความยาวที่จำกัดจำนวนตัวอักษร
- ✅ **Scroll to error**: Filament เลื่อนไปช่องแรกที่ผิดให้เอง
- ➖ **Scroll to top**: หน้าแต่ละหน้าสั้นและแบ่งหน้าแล้ว
- ✅ **Prevent accidental actions**: มีการยืนยันก่อน และการลบทำได้เฉพาะของที่ว่าง
- ✅ **Enter submit**
- ✅ **Tab navigation**

## 12. File upload

- ➖ ยังไม่มีการอัปโหลดไฟล์ (ในแผนเดิมมีตาราง attachments ไว้สำหรับเฟสถัดไป)

## 13. Performance

- ✅ **รูป optimize**: favicon เป็น SVG และภาพใน README ย่อเหลือกว้าง 1200px
- ✅ **Lazy loading**: widget บน dashboard
- ✅ **Code splitting**: Filament โหลด JS แยกตาม component
- ✅ **Cache**: รัน `php artisan optimize` ตอน container start เพื่อ cache config, route และ view
- ✅ **API ไม่ยิงซ้ำโดยไม่จำเป็น**: ปิด polling ของ widget แล้ว
- ✅ **Debounce search**: ค้นหาหน่วง 500ms
- ✅ **Pagination ข้อมูลเยอะ**: มี index รองรับการกรองและนับที่ใช้บ่อย
- 🛠 **N+1 query**:
  - เปิด `Model::preventLazyLoading()` ตอน dev และ test
  - smoke test เปิดทุกหน้าแล้วไม่พบการโหลดความสัมพันธ์แบบ lazy
- ⚠️ **หน้าแรกโหลดเร็ว**: Render แผนฟรีจะหลับเมื่อไม่มีคนเข้าใช้ ทำให้คนแรกที่เข้ามาหลังจากนั้นต้องรอประมาณ 1 นาที

## 14. Security

- ✅ **Validation ทั้ง frontend และ backend**: ตรวจที่เซิร์ฟเวอร์ และมี constraint กับ trigger ใน database
- ✅ **Authorization ฝั่ง backend**:
  - ใช้ policy และ query scope
  - เปิด URL ตรง ๆ ก็ได้ 403 หรือ 404
- ✅ **Password ไม่เก็บ plain text**: ใช้ cast `hashed`
- ✅ **Sensitive data ไม่อยู่ใน client**: Livewire ส่งไปที่ client เฉพาะ id ของ model
- 🛠 **ไม่ส่งข้อมูลผู้ใช้ออกไปเว็บภายนอก**: avatar เดิมดึงจาก ui-avatars.com ซึ่งต้องส่งชื่อผู้ใช้ไปด้วย ตอนนี้วาดเป็น SVG ในระบบเอง
- ✅ **ป้องกัน XSS**: Blade escape อัตโนมัติ และไม่มี HTML จากผู้ใช้
- ✅ **ป้องกัน SQL injection**: ใช้ query builder และ binding ทุก query รวมถึง `selectRaw`
- ➖ **CORS**: ไม่มี API ที่เรียกข้ามโดเมน
- ✅🛠 **Rate limit**: login และลิงก์ demo
- 🛠 **Secure cookie**:
  - บน Render ตั้ง `SESSION_SECURE_COOKIE=true`
  - HttpOnly และ SameSite=lax เป็นค่าเริ่มต้นอยู่แล้ว
- ✅ **Environment variables**:
  - `.env` อยู่ใน gitignore
  - `.env.example` ปิดโหมด demo ไว้
  - `APP_KEY` สร้างตอน start
- ✅ **ไม่ commit secret / API key**

## 15. ก่อนส่งงาน / Deploy

- ✅ **ไม่มี console error**: smoke test ตรวจทุกหน้า
- ✅ **ไม่มี debug log ที่ไม่จำเป็น**
- ✅ **ไม่มี lorem ipsum**
- ✅ **ไม่มีปุ่มที่กดไม่ได้ / dead link**: smoke test เปิดทุกหน้าตามบทบาท
- ✅🛠 **Favicon / title**: favicon เคยถูกสร้างเป็นลิงก์ `http://` บนเว็บจริง (Render รับ HTTPS ที่ proxy) จน browser block เพราะเป็น mixed content แก้ให้สร้าง URL ตอน render หน้า และมี test ครอบไว้แล้ว
- 🛠 **Meta description**
- 🛠 **404**
- ✅ **Loading / empty / error / responsive**
- ✅ **Test login / logout / CRUD / permission**: `php artisan test`
- ✅ **Test refresh page**
- ✅ **Test production**: smoke test กับ Docker image ที่ตั้ง `APP_ENV=production`
- ✅ **Test หลัง deploy จริง**: `node tests/Browser/smoke.mjs https://asset-laravel.onrender.com` (รอบแรกเจอปัญหา favicon ข้างบน ซึ่ง Docker ในเครื่องจับไม่ได้เพราะไม่มี HTTPS proxy)

---

## วิธีทดสอบ

```bash
php artisan test                                       # 36 tests: กฎใน database, การกดปุ่มจริงใน UI, สิทธิ์, ข้อความภาษาไทย
php artisan serve &                                    # ต้องตั้ง APP_DEMO=true
node tests/Browser/smoke.mjs http://127.0.0.1:8000     # เปิด Chrome จริง: ทุกหน้า, ESC ปิด modal, back/forward, 403/404
```

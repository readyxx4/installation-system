# Skills Guide

คู่มือนี้ใช้สำหรับโปรเจกต์ `installation_system` หลังติดตั้ง Impeccable แบบ project-local สำหรับ Codex แล้ว

## สถานะการติดตั้ง

- Impeccable skill อยู่ที่ `.agents/skills/impeccable`
- Codex hook อยู่ที่ `.codex/hooks.json`
- Hook ของโปรเจกต์ถูก approve แล้วใน Codex
- ไฟล์ runtime ชั่วคราวของ Impeccable ถูกกันไว้ใน `.gitignore`

## เริ่มใช้งานครั้งแรก

พิมพ์ในช่อง Codex ของ VS Code:

```text
/impeccable init
```

ถ้า slash command ไม่ทำงาน ให้ใช้ข้อความนี้แทน:

```text
Use the Impeccable skill and initialize design context for this project.
```

## คำสั่งหลักที่ให้ใช้ทุกครั้งเมื่อปรับ UI

ทุกครั้งที่สั่งแก้ UI, layout, dashboard, table, form หรือหน้าตาเว็บ ให้ใช้คำสั่งแนวนี้เป็นค่าเริ่มต้น:

```text
/impeccable polish <ไฟล์หรือหน้าที่ต้องการแก้> รักษา business logic เดิม ห้ามแก้ SQL query, session, permission, routing, form action, API endpoint, AJAX payload, status value หรือ sidebar ปรับเฉพาะ alignment, spacing, typography, responsive behavior และรายละเอียด UI
```

ตัวอย่าง:

```text
/impeccable polish sale/setups.php รักษา business logic เดิม ห้ามแก้ SQL query, session, permission, routing, form action, API endpoint, AJAX payload, status value หรือ sidebar ปรับเฉพาะ alignment, spacing, typography, responsive behavior และรายละเอียด UI
```

## คำสั่งที่ใช้บ่อย

ตรวจคุณภาพ UI/UX:

```text
/impeccable audit admin/users.php
```

ขัด UI ให้ดูดีขึ้น:

```text
/impeccable polish admin/users.php
```

ปรับ typography และ spacing:

```text
/impeccable typeset admin/users.php
```

ลดความรกของหน้า:

```text
/impeccable distill admin/users.php
```

## Prompt ที่แนะนำสำหรับโปรเจกต์นี้

ใช้กับหน้า admin:

```text
/impeccable polish admin/users.php รักษา business logic เดิม ห้ามแก้ query, session, permission, routing หรือ sidebar ปรับเฉพาะ alignment, spacing, typography และรายละเอียด UI
```

ใช้กับหน้า sale:

```text
/impeccable polish sale/setups.php รักษา business logic เดิม ห้ามแก้ query, session, permission, routing หรือ sidebar ปรับเฉพาะ alignment, spacing, typography และรายละเอียด UI
```

ใช้ตรวจอย่างเดียวก่อนแก้:

```text
/impeccable audit admin/index.php แล้วสรุปปัญหา UI ก่อน ยังไม่ต้องแก้ไฟล์
```

## ข้อควรระวัง

- ก่อนให้ Impeccable แก้ไฟล์ ควรตรวจ `git status` ก่อนเสมอ
- อย่าให้แก้ business logic, SQL query, session, permission, routing หรือ sidebar ถ้าไม่ได้ตั้งใจ
- เริ่มจากไฟล์เดียวก่อน เช่น `admin/users.php` หรือ `sale/setups.php`
- หลังแก้เสร็จ ให้ตรวจ diff ก่อน commit
- ห้าม commit หรือ push อัตโนมัติ ถ้ายังไม่ได้ตรวจเอง

## ตรวจจาก command line

ถ้าต้องการตรวจแบบอ่านอย่างเดียว:

```powershell
npx.cmd impeccable detect admin/index.php
```

หรือระบุไฟล์อื่น:

```powershell
npx.cmd impeccable detect sale/setups.php
```

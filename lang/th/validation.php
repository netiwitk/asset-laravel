<?php

/*
 * Thai validation messages for the rules this app uses. Filament passes each field's label
 * as :attribute, so messages read e.g. "กรุณากรอกรหัสผ่าน".
 */
return [

    'accepted' => 'กรุณายอมรับ:attribute',
    'after' => ':attribute ต้องเป็นวันที่หลัง :date',
    'after_or_equal' => ':attribute ต้องเป็นวันที่ :date หรือหลังจากนั้น',
    'before' => ':attribute ต้องเป็นวันที่ก่อน :date',
    'before_or_equal' => ':attribute ต้องเป็นวันที่ :date หรือก่อนหน้านั้น',
    'between' => [
        'numeric' => ':attribute ต้องอยู่ระหว่าง :min ถึง :max',
        'string' => ':attribute ต้องยาว :min ถึง :max ตัวอักษร',
    ],
    'boolean' => ':attribute ต้องเป็นใช่หรือไม่ใช่',
    'confirmed' => ':attribute กับช่องยืนยันไม่ตรงกัน',
    'date' => ':attribute ต้องเป็นวันที่ที่ถูกต้อง',
    'decimal' => ':attribute ต้องมีทศนิยม :decimal ตำแหน่ง',
    'different' => ':attribute ต้องไม่ซ้ำกับ :other',
    'email' => ':attribute ต้องเป็นอีเมลที่ถูกต้อง',
    'exists' => ':attribute ที่เลือกไม่มีในระบบ',
    'filled' => 'กรุณากรอก:attribute',
    'gt' => ['numeric' => ':attribute ต้องมากกว่า :value'],
    'gte' => ['numeric' => ':attribute ต้องไม่น้อยกว่า :value'],
    'in' => ':attribute ที่เลือกไม่ถูกต้อง',
    'integer' => ':attribute ต้องเป็นจำนวนเต็ม',
    'lt' => ['numeric' => ':attribute ต้องน้อยกว่า :value'],
    'lte' => ['numeric' => ':attribute ต้องไม่เกิน :value'],
    'max' => [
        'array' => ':attribute เลือกได้ไม่เกิน :max รายการ',
        'numeric' => ':attribute ต้องไม่เกิน :max',
        'string' => ':attribute ต้องยาวไม่เกิน :max ตัวอักษร',
    ],
    'min' => [
        'array' => ':attribute ต้องเลือกอย่างน้อย :min รายการ',
        'numeric' => ':attribute ต้องไม่น้อยกว่า :min',
        'string' => ':attribute ต้องยาวอย่างน้อย :min ตัวอักษร',
    ],
    'not_in' => ':attribute ที่เลือกไม่ถูกต้อง',
    'numeric' => ':attribute ต้องเป็นตัวเลข',
    'regex' => 'รูปแบบของ:attribute ไม่ถูกต้อง',
    'required' => 'กรุณากรอก:attribute',
    'required_if' => 'กรุณากรอก:attribute',
    'required_with' => 'กรุณากรอก:attribute',
    'same' => ':attribute ต้องตรงกับ :other',
    'string' => ':attribute ต้องเป็นข้อความ',
    'unique' => ':attribute นี้ถูกใช้แล้ว',
    'url' => ':attribute ต้องเป็นลิงก์ที่ถูกต้อง',

    'attributes' => [],

];

@extends('errors.layout')

@section('code', 'ERROR 419')
@section('title', 'เซสชันหมดอายุ')
@section('message', 'คุณไม่ได้ใช้งานนานเกินไป กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง')
@section('actions')<button type="button" class="ghost" onclick="location.reload()">โหลดหน้าใหม่</button>@endsection

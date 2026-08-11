@extends('layouts.app')

@section('title', 'Movie AI - AI映画提案')

@section('content')
<div class="hero-section">
    <h1 class="hero-title">どんな気分ですか？</h1>
    <p class="hero-subtitle">AIがあなたのための最高の映画を見つけ出します。</p>
    
    <div class="search-container">
        <input type="text" class="search-input" placeholder="例：どんでん返しがあるSF映画が見たい">
        <button class="search-btn">提案をもらう</button>
    </div>
</div>
@endsection

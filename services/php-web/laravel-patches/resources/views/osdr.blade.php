@extends('layouts.app')

@section('content')
<div class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h3 class="mb-0 text-primary">NASA Open Science Data Repository (OSDR)</h3>
        <a href="/iss" class="btn btn-outline-secondary btn-sm btn-hover">
            <i class="bi bi-arrow-left me-1"></i> Назад к МКС
        </a>
    </div>

    {{-- Секция фильтрации --}}
    <div class="row mb-3 align-items-center">
        <div class="col-md-4">
            <div class="text-muted small mb-2 mb-md-0">
                Источник: <code>{{ $src }}</code>
            </div>
        </div>
        
        <div class="col-md-8 d-flex justify-content-md-end gap-3 align-items-center flex-wrap">
            {{-- JS Фильтр --}}
            <div class="input-group input-group-sm" style="max-width: 250px;">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="tableSearch" placeholder="Поиск по ID или названию...">
            </div>

            <div class="btn-group btn-group-sm" role="group" aria-label="Limit filter">
                <span class="btn btn-light text-muted">Лимит:</span>
                @foreach([20, 50, 100] as $l)
                    <a href="?limit={{ $l }}" class="btn {{ (isset($currentLimit) && $currentLimit == $l) ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $l }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    
    {{-- Таблица данных OSDR --}}
    <div class="table-responsive shadow-sm rounded bg-white hover-card">
        <table class="table table-hover table-sm align-middle mb-0" id="osdrTable">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 150px;">Dataset ID</th>
                    <th style="min-width: 300px;">Заголовок / Имя</th>
                    <th>REST URL</th>
                    <th>Статус</th>
                    <th style="width: 150px;">Обновлено</th>
                    <th style="width: 150px;">Создано</th>
                    <th style="width: 80px;">Raw</th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $row)
                @php
                    $updated = $row['updated_at'] ?? $row['UpdatedAt'] ?? null;
                    $inserted = $row['inserted_at'] ?? $row['InsertedAt'] ?? null;
                    $status = $row['status'] ?? $row['Status'] ?? 'N/A';
                    $statusColor = match(strtolower($status)) {
                        'new' => 'info',
                        'processed' => 'success',
                        'error' => 'danger',
                        default => 'secondary'
                    };
                @endphp
                {{-- Добавили класс table-row-anim для плавности --}}
                <tr class="table-row-anim main-row">
                    <td class="small text-muted search-id">{{ $row['id'] ?? $row['ID'] ?? '—' }}</td>
                    <td><code class="fw-bold search-dataset">{{ $row['dataset_id'] ?? '—' }}</code></td>
                    <td class="search-title" title="{{ $row['title'] ?? '—' }}" style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $row['title'] ?? '—' }}
                    </td>
                    <td>
                        @if(!empty($row['rest_url']))
                            <a href="{{ $row['rest_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 btn-hover">открыть <i class="bi bi-box-arrow-up-right"></i></a>
                        @else — @endif
                    </td>
                    <td>
                         <span class="badge bg-{{ $statusColor }} text-uppercase">{{ $status }}</span>
                    </td>
                    <td class="small">
                        @if($updated)
                            {{ \Carbon\Carbon::parse($updated)->diffForHumans() }}
                        @else — @endif
                    </td>
                    <td class="small">
                        @if($inserted)
                            {{ \Carbon\Carbon::parse($inserted)->format('Y-m-d') }}
                        @else — @endif
                    </td>
                    <td>
                        <button class="btn btn-outline-secondary btn-sm py-0 btn-hover" data-bs-toggle="collapse" data-bs-target="#raw-{{ $loop->iteration }}">JSON</button>
                    </td>
                </tr>
                {{-- Строка с RAW JSON --}}
                <tr class="collapse detail-row" id="raw-{{ $loop->iteration }}">
                    <td colspan="8" class="bg-light p-2 border-bottom">
                        <pre class="mb-0 small" style="max-height:260px;overflow:auto;">{{ json_encode($row['raw'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Нет данных или ошибка запроса</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    
    <div id="noResults" class="text-center text-muted mt-3" style="display: none;">
        <i class="bi bi-search"></i> Совпадений не найдено
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('tableSearch');
    const table = document.getElementById('osdrTable');
    const noResultsMsg = document.getElementById('noResults');
    
    searchInput.addEventListener('keyup', function(e) {
        const term = e.target.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr.main-row');
        let hasVisible = false;

        rows.forEach(row => {
            const id = row.querySelector('.search-id')?.textContent.toLowerCase() || '';
            const dataset = row.querySelector('.search-dataset')?.textContent.toLowerCase() || '';
            const title = row.querySelector('.search-title')?.textContent.toLowerCase() || '';
            
            const detailRow = row.nextElementSibling;

            if (id.includes(term) || dataset.includes(term) || title.includes(term)) {
                row.style.display = '';
                hasVisible = true;
            } else {
                row.style.display = 'none';
                if (detailRow && detailRow.classList.contains('detail-row')) {
                    detailRow.style.display = 'none'; 
                    detailRow.classList.remove('show'); 
                }
            }
            
            if (row.style.display === '') {
                 if (detailRow && detailRow.classList.contains('detail-row')) {
                    detailRow.style.display = ''; 
                 }
            }
        });

        noResultsMsg.style.display = hasVisible ? 'none' : 'block';
    });
});
</script>
@endsection
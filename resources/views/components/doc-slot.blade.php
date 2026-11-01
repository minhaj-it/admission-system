@props(['application', 'type', 'document' => null, 'required' => false])
@php $url = $document ? route('documents.show', [$application, $document]) : null; @endphp
@if ($type->kind === 'image')
    <div class="photo-box" data-slot>
        <div class="ph">
            @if ($document && $document->isImage())<img src="{{ $url }}" alt="{{ $type->name }}">@else<i class="bi bi-person-fill"></i>@endif
        </div>
        <div class="small fw-semibold mb-1">{{ $type->name }}@if($required) *@endif</div>
        <input type="file" id="doc_{{ $type->id }}" name="doc_{{ $type->id }}" accept="image/png,image/jpeg,image/webp" hidden data-photo-input>
        <div class="d-flex gap-2 justify-content-center">
            <button type="button" class="btn btn-soft btn-sm rounded-pill" data-photo-change><i class="bi bi-pencil-fill text-primary me-1"></i>{{ $document ? 'Change Photo' : 'Upload Photo' }}</button>
            @if ($document)
                <label class="btn btn-remove btn-sm m-0"><input type="checkbox" name="remove_docs[]" value="{{ $document->id }}" hidden data-photo-remove><i class="bi bi-trash-fill me-1"></i>Remove</label>
            @endif
        </div>
        @if ($document && $document->verified_status !== 'pending')
            <div class="small mt-1 {{ $document->verified_status === 'rejected' ? 'text-danger' : 'text-success' }}">{{ ucfirst($document->verified_status) }}@if($document->remark): {{ $document->remark }}@endif</div>
        @endif
        @error('doc_' . $type->id)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
@else
    <div class="col-md-6">
        <label class="form-label" for="doc_{{ $type->id }}">{{ $type->name }}@if($required) *@endif</label>
        <input type="file" class="form-control @error('doc_' . $type->id) is-invalid @enderror" id="doc_{{ $type->id }}" name="doc_{{ $type->id }}" accept=".pdf,.jpg,.jpeg,.png,.webp">
        @error('doc_' . $type->id)<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($document)
            <div class="small mt-1">
                <a href="{{ $url }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> View uploaded file</a>
                <label class="ms-2 text-danger"><input type="checkbox" name="remove_docs[]" value="{{ $document->id }}"> Remove</label>
                @if ($document->verified_status !== 'pending')
                    <span class="ms-2 {{ $document->verified_status === 'rejected' ? 'text-danger' : 'text-success' }}">{{ ucfirst($document->verified_status) }}@if($document->remark): {{ $document->remark }}@endif</span>
                @endif
            </div>
        @endif
    </div>
@endif

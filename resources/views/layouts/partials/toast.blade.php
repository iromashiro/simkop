<template x-for="toast in $store.toast.items" :key="toast.id">
    <div class="toast show" role="alert" x-data="{ show: true }" x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2">
        <div class="toast-header" :class="{
                'bg-success text-white': toast.type === 'success',
                'bg-danger text-white': toast.type === 'error',
                'bg-warning text-dark': toast.type === 'warning',
                'bg-info text-white': toast.type === 'info'
             }">
            <i class="bi me-2" :class="{
                   'bi-check-circle': toast.type === 'success',
                   'bi-exclamation-triangle': toast.type === 'error',
                   'bi-exclamation-triangle': toast.type === 'warning',
                   'bi-info-circle': toast.type === 'info'
               }"></i>
            <strong class="me-auto" x-text="toast.type === 'success' ? 'Berhasil' :
                                           toast.type === 'error' ? 'Error' :
                                           toast.type === 'warning' ? 'Peringatan' : 'Info'"></strong>
            <button type="button" class="btn-close" @click="$store.toast.remove(toast.id)"
                :class="{ 'btn-close-white': toast.type !== 'warning' }"></button>
        </div>
        <div class="toast-body" x-text="toast.message"></div>
    </div>
</template>

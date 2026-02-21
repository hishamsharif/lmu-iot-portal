export async function callLivewireMethod(livewireId, methodName, payload = undefined) {
    if (typeof window.Livewire === 'undefined' || livewireId === '') {
        return null;
    }

    const livewireComponent = window.Livewire.find(livewireId);
    if (!livewireComponent) {
        return null;
    }

    if (typeof payload === 'undefined') {
        return livewireComponent.call(methodName);
    }

    return livewireComponent.call(methodName, payload);
}

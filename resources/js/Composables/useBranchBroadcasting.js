import { onBeforeUnmount, onMounted } from 'vue';

export function useBranchBroadcasting(branchId, callback) {
    const legacyChannelName = `branches.${branchId}.calendar`;
    const unifiedChannelName = `branches.${branchId}.events`;

    onMounted(() => {
        window.Echo
            .private(legacyChannelName)
            .listen('.calendar.updated', (event) => {
                callback(event);
            });

        window.Echo
            .private(unifiedChannelName)
            .listen('.event.updated', (event) => {
                callback(event);
            });
    });

    onBeforeUnmount(() => {
        window.Echo?.leave(legacyChannelName);
        window.Echo?.leave(unifiedChannelName);
    });
}
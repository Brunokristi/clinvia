import { onBeforeUnmount, onMounted } from 'vue';

export function useBranchInboxBroadcasting(branchId, callback) {
    const channelName = `branches.${branchId}.inbox`;

    onMounted(() => {
        window.Echo
            .private(channelName)
            .listen('.inbox.updated', (event) => {
                callback(event);
            });
    });

    onBeforeUnmount(() => {
        window.Echo?.leave(channelName);
    });
}
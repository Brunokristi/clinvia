import { onBeforeUnmount } from 'vue';

export const useBranchBroadcasting = (branchId, callback) => {
    if (!branchId || typeof window === 'undefined' || !window.Echo) {
        return;
    }

    const channel = window.Echo.private(`branch.${branchId}`);

    channel.listen('.branch.booking.created', callback);
    channel.listen('.branch.appointment-request.created', callback);

    onBeforeUnmount(() => {
        channel.stopListening('.branch.booking.created');
        channel.stopListening('.branch.appointment-request.created');
        window.Echo.leave(`branch.${branchId}`);
    });
};

/**
 * Keeps a scrollable chat transcript pinned to its newest message — including
 * while an answer streams in token by token — but stops fighting the user the
 * moment they scroll up to re-read something, and re-pins when they come back
 * down. Used as x-data="chatTranscript" on the transcript container.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chatTranscript', () => ({
        /** How close to the bottom still counts as "following along" (px). */
        threshold: 120,

        pinned: true,

        init() {
            this.scrollToLatest();

            this.$el.addEventListener('scroll', () => {
                const distance = this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight;

                this.pinned = distance < this.threshold;
            });

            // Streamed text arrives as character-data changes, new turns as new
            // nodes, so both are observed.
            new MutationObserver(() => this.scrollToLatest()).observe(this.$el, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        },

        scrollToLatest() {
            if (this.pinned) {
                this.$el.scrollTop = this.$el.scrollHeight;
            }
        },
    }));
});

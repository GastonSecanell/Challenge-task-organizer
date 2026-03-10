import { defineStore } from 'pinia'

export const useCardEventsStore = defineStore('cardEvents', {
  state: () => ({
    version: 0,
    lastEvent: null,
    activityVersions: {},
    cardVersions: {},
  }),

  actions: {
    emitEvent(event) {
      const cardId = Number(event?.cardId)
      this.lastEvent = event
      this.version += 1

      if (cardId) {
        this.cardVersions[cardId] = (this.cardVersions[cardId] || 0) + 1

        if (
          [
            'comment-created',
            'comment-deleted',
            'attachment-created',
            'attachment-deleted',
            'checklist-created',
            'checklist-deleted',
            'checklist-toggled',
            'card-refresh',
            'card-done-changed',
          ].includes(event.type)
        ) {
          this.activityVersions[cardId] = (this.activityVersions[cardId] || 0) + 1
        }
      }
    },

    bumpActivity(cardId) {
      const id = Number(cardId)
      if (!id) return
      this.activityVersions[id] = (this.activityVersions[id] || 0) + 1
      this.lastEvent = { type: 'activity-refresh', cardId: id }
      this.version += 1
    },

    bumpCard(cardId) {
      const id = Number(cardId)
      if (!id) return
      this.cardVersions[id] = (this.cardVersions[id] || 0) + 1
      this.lastEvent = { type: 'card-refresh', cardId: id }
      this.version += 1
    },

    getActivityVersion(cardId) {
      return this.activityVersions[Number(cardId)] || 0
    },

    getCardVersion(cardId) {
      return this.cardVersions[Number(cardId)] || 0
    },
  },
})
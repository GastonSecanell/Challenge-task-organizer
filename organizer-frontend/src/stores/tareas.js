import { defineStore } from 'pinia'
import { TareasApi } from '@/lib/api/tareas'

export const useTareasStore = defineStore('tareas', {

  state: () => ({
    tareas: [],
    loading: false,
  }),

  actions: {

    async fetchTareas() {
      this.loading = true

      try {
        const res = await TareasApi.list()
        this.tareas = res.data ?? res
      } finally {
        this.loading = false
      }
    },

    async createTarea(payload) {
      await TareasApi.create(payload)
      await this.fetchTareas()
    },

    async updateTarea(id, payload) {
      await TareasApi.update(id, payload)
      await this.fetchTareas()
    },

    async deleteTarea(id) {
      await TareasApi.remove(id)
      await this.fetchTareas()
    },

  },

})
import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useIncidentStore = defineStore('Incidents', {
  state: () => ({
    statusLabels: [
      '',
      'Pendiente',
      'Atendido',
      'Pendiente de aprobación',
      'Resuelto',
    ],
    typeLabels: [
      '',
      '🔧 Avería de infraestructura',
      '⚙️ Avería de equipos (gimnasio, ascensores, etc.)',
      '⚠️ Incumplimiento de normas',
      '📢 Reclamo',
      '❓ Consulta',
      '🏢 Falla general',
      '👤 Conducta inadecuada de un residente',
      '🛡️ Incidente con personal del edificio',
      '📝 Otro',
    ]
  }),
  actions: {
    async getIncidents() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();

        ApiService.get('/api/incidents')
          .then(({ data }) => {
            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al obtener incidencias');
          });
      })
    },
    async getIncidentById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();

        ApiService.get(`/api/incidents/byId/${id}`)
          .then(({ data }) => {
            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al obtener la incidencia');
          });
      })
    },
    async createIncident(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();

        ApiService.post('/api/incidents', data)
          .then(({ data }) => {
            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al crear la incidencia');
          });
      })
    }
  },
})

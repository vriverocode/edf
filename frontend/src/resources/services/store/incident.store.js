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
      'Consulta por duda',
      'Reclamos',
      'Averias de infraestructura',
      'Averias en equipos(Ascensores, caminadoras, butacas)',
      'Incumplimiento de reglas y normativas',
      'Fallas generales',
      'Maltrato por parte de propietario',
      'Otros'
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

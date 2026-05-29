<script setup>
import { ref, onMounted } from 'vue';
import { useQuotaStore } from '@/services/store/quota.store';
import { useRouter } from 'vue-router';
import moment from 'moment';
import appIcons from '@/assets/icons';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
});

const quotas = ref([]);
const loading = ref(true);
const quotaStore = useQuotaStore();
const router = useRouter();
const filters = ref({
  status: 4,
  sort_by: 'created_at',
  sort_dir: 'desc',
});

const getQuotas = () => {
  loading.value = true;
  quotaStore.getMonthlyGlobalQuotasForAdmin(filters.value)
    .then((response) => {
      if (response.code !== 200) throw response;
      quotas.value = response.data;
    })
    .catch((response) => {
      console.log(response);
    })
    .finally(() => {
      loading.value = false;
    });
};

const goTo = (quota) => {
  router.push(`/admin/quota/details/month/${quota.month}?year=${getYearOfQuota(quota)}&owner=${quota.details[0].departament.owner.id}`);
};

const getTitleQuota = (quota) => {
  const year = quota.year ? ` ${quota.year}` : '';
  return `Mensualidad: ${quota.details[0].month_label}${year}`;
};

const getStatusInfo = (status) => {
  if (status === 1) {
    return { color: 'warning', icon: 'eva-alert-circle-outline', label: 'Pendiente' };
  }
  return { color: 'positive', icon: 'eva-checkmark-circle-2-outline', label: 'Pagado' };
};

const formatDate = (date) => {
  if (!date) return '';
  return moment(date).format('DD MMM YYYY');
};
const getYearOfQuota = (quota) => {
  return  moment(quota.due_date).format('YYYY')
} 
const getPaymentProgress = (quota) => {
  const details = Array.isArray(quota.details) ? quota.details : [];
  const total = details.length;
  if (!total) return '0/0 (0%)';

  const paid = details.filter((item) => Number(item.status) !== 1).length;
  const percent = Math.round((paid / total) * 100);
  return `${paid}/${total} (${percent}%)`;
};

const unitsInQuota = (quota) => {
  let apartmentNumbers = '';
  quota.details.forEach((element,index) => {
    apartmentNumbers += element.departament.number

    if(index+1 < quota.details.length){
      apartmentNumbers += ' - ' 
    }
  });

  return apartmentNumbers 
}

onMounted(() => {
  getQuotas();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="h-full" style="overflow: auto;">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 pt-6 md:px-28 pb-20">
        <div v-if="quotas.length > 0" class="space-y-5 md:px-5">
          <div v-for="quota in quotas" :key="quota.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative; border: 1px solid lightgrey">

            <div class="px-4 pb-2 pt-2 md:pt-4">
              <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-1">
                    {{ getTitleQuota(quota) }} - {{ getYearOfQuota(quota)}}
                    <span v-if="quota.created_at && moment(quota.created_at).isAfter(moment().subtract(7, 'days'))"
                      class="absolute top-2 right-2 bg-primary text-white text-xs font-bold px-2 py-1 rounded-md">
                      Nuevo
                    </span>
                  </h3>
                  <div class="row">
                    <div class="flex items-center text-sm text-gray-700 col-6 pb-1 md:pt-0 col-md-2 ">
                      <svg style="transform: translateX(-3px);" width="15px" height="15px"  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M5 21C5 17.134 8.13401 14 12 14C15.866 14 19 17.134 19 21M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7Z" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                      <span class="font-medium">{{ quota.owner_name}}</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-700 col-6 pb-1 md:pt-0 col-md-2 ">
                      <svg style="transform: translateX(-3px);" width="23px" height="23px" viewBox="0 0 64 64"
                        xmlns="http://www.w3.org/2000/svg" stroke-width="2" stroke="#374151" fill="none">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier">
                          <path d="M34.82,52.73H14.69V22.18a1,1,0,0,1,.52-.87L33.34,11.4a1,1,0,0,1,1.48.88Z"
                            stroke-linecap="round"></path>
                          <path d="M48.87,52.73H34.92V21.59L48.4,29.3a1,1,0,0,1,.47.85Z" stroke-linecap="round"></path>
                          <line x1="28.1" y1="24.86" x2="21.06" y2="24.86" stroke-linecap="round"></line>
                          <line x1="43.66" y1="32.41" x2="40.14" y2="32.41" stroke-linecap="round"></line>
                          <line x1="43.66" y1="36.9" x2="40.14" y2="36.9" stroke-linecap="round"></line>
                          <line x1="43.66" y1="41.71" x2="40.14" y2="41.71" stroke-linecap="round"></line>
                          <line x1="43.66" y1="46.19" x2="40.14" y2="46.19" stroke-linecap="round"></line>
                          <line x1="28.1" y1="30.44" x2="21.06" y2="30.44" stroke-linecap="round"></line>
                          <line x1="28.1" y1="35.94" x2="21.06" y2="35.94" stroke-linecap="round"></line>
                          <line x1="28.1" y1="41.44" x2="21.06" y2="41.44" stroke-linecap="round"></line>
                          <line x1="28.1" y1="46.94" x2="21.06" y2="46.94" stroke-linecap="round"></line>
                          <line x1="9.46" y1="52.73" x2="54.54" y2="52.73" stroke-linecap="round"></line>
                        </g>
                      </svg>
                      <span class="font-medium"> {{ unitsInQuota(quota) }}</span>
                    </div>
                  </div>
                  <!-- <div class="text-xs text-gray-500 mb-2">{{ quota.description }}</div> -->
                </div>
              </div>

              <div class="space-y-2 pt-3">
                <div class="row items-center ">
                  <!-- Monto -->
                  <div class="flex items-center text-sm text-gray-700 pl-0 md:pl-0 col-4 col-md-2 ">
                    <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                      </path>
                    </svg>
                    <span class="font-medium">S/. {{ Number(quota.maintenance_amount).toFixed(2) }}</span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700 pl-2 md:pl-0 col-4 col-md-2 ">
                    <svg class="w-4 h-4 mr-1 text-gray-500" fill="#6a7282" version="1.1" id="Layer_1"
                      xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                      viewBox="0 0 511.999 511.999" xml:space="preserve" stroke="#6a7282">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier">
                        <g>
                          <g>
                            <path
                              d="M451.302,300.843V131.949h-92.379v15.872h76.508v153.022h-89.688V253.38h60.678v-15.872H216.415v15.872h113.456v47.463 H308.76v68.651h163.656v-68.651H451.302z M456.543,353.623H324.631v-36.907h131.912V353.623z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M337.807,147.821v-15.872h-92.344v-58.02h52.78V0H124.032v73.929h52.78v58.02H124.07v-26.39H55.456V81.828H39.584v221.673 h15.872V279.77h68.614v-26.39h71.233v-15.872H124.07v-89.688H337.807z M229.591,131.949h-0.001h-36.906v-58.02h36.907V131.949z M139.903,58.057V42.261h102.901V26.39H139.903V15.872H282.37v42.185H139.903z M108.198,263.898H55.456V121.431h52.742V263.898z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="71.253" y="139.884" width="15.872" height="63.335"></rect>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="71.253" y="219.052" width="15.872" height="21.112"></rect>
                          </g>
                        </g>
                        <g>
                          <g>
                            <polygon
                              points="290.306,163.616 290.306,179.488 403.763,179.488 403.763,203.221 419.635,203.221 419.635,163.616 ">
                            </polygon>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="242.807" y="163.617" width="26.39" height="15.872"></rect>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M401.748,387.902l-5.883-6.504l-5.885,6.504c-6.11,6.753-59.243,66.982-43.742,101.913 c6.533,14.721,23.229,22.184,49.626,22.184c26.397,0,43.094-7.464,49.626-22.184C460.992,454.885,407.859,394.654,401.748,387.902 z M430.984,483.378c-4.677,10.538-21.679,12.75-35.118,12.75c-13.438,0-30.439-2.211-35.117-12.746 c-4.465-10.052,0.98-28.078,15.329-50.758c7.025-11.103,14.723-20.994,19.84-27.221 C415.524,428.955,438.75,465.88,430.984,483.378z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="380.035" y="464.463" width="21.111" height="15.872"></rect>
                          </g>
                        </g>
                      </g>
                    </svg>
                    <span class="font-medium">
                      S/. {{ Number(quota.water_amount).toFixed(2) }}
                    </span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700 pl-2 md:pl-0 col-4 col-md-2 ">
                    <svg fill="#6a7282" class="w-5 h-5 mr-2 text-gray-500" version="1.1" id="Layer_1"
                      xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                      viewBox="0 0 480 480" xml:space="preserve" stroke="#6a7282" stroke-width="7.68">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier">
                        <g>
                          <g>
                            <path
                              d="M240.008,0c-0.192,0-0.384,0-0.576,0c-23.496,0-45.528,10.008-62.04,26.6C160.872,43.208,151.848,65.528,152,88.88 c0.312,47.976,39.728,87.12,87.992,87.12c0.192,0,0.384,0,0.576,0c23.496-0.152,45.52-9.368,62.032-25.96 c16.528-16.608,25.544-39.248,25.4-62.6C327.696,39.464,288.272,0,240.008,0z M291.256,138.752 c-13.504,13.584-31.544,21.128-50.792,21.248c-39.84-0.056-72.208-31.552-72.464-70.904c-0.12-19.048,7.24-37.648,20.736-51.2 C202.256,24.312,220.296,16,239.536,16c0.16,0,0.312,0,0.472,0c39.496,0,71.744,32.352,71.992,71.544 C312.128,106.592,304.76,125.192,291.256,138.752z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M247.944,79.896L240.152,80c-0.016,0-0.032,0-0.048,0c-4.384,0-7.976-3.608-8-8c-0.024-4.328,3.424-7.88,7.72-8.032 c0.096,0,0.168,0.024,0.264,0.024c0.016,0,0.032,0,0.048,0c0.176,0,0.328-0.096,0.504-0.104l23.216-0.152 c4.416-0.032,7.976-3.504,7.944-7.928c-0.032-4.4-3.6-7.816-8-7.816c-0.016,0-0.032,0-0.056,0l-15.712-0.024L248,41.608 c-0.024-4.392-3.608-9.608-8-9.608c-0.016,0-0.032,0-0.048,0c-4.424,0-7.976,5.232-7.952,9.656l0.04,6.944 c-9.312,3.352-16,12.656-15.936,23.104C216.184,84.888,226.944,96,240.112,96c0.048,0,0.104,0,0.152,0h7.792 c0.024,0,0.04,0,0.056,0c2.12,0,4.112,0.744,5.624,2.224c1.512,1.504,2.36,3.472,2.376,5.6c0.032,4.416-3.536,8.008-7.952,8.032 L224.368,112c-4.424,0.032-7.976,3.624-7.952,8.048c0.024,4.4,3.608,7.944,8,7.944c0.016,0,0.032,0,0.048,0l7.592-0.048 l0.032,6.504c0.024,4.4,3.608,9.544,8,9.544c0.016,0,0.032,0,0.048,0c4.416,0,7.976-5.16,7.944-9.576L248.048,128h0.2 c13.232,0,23.928-10.968,23.848-24.2S261.576,79.8,247.944,79.896z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M25.376,168C11.384,168,0,179.928,0,194.6c0,14.672,11.384,26.6,25.376,26.6c14,0,25.384-11.928,25.384-26.6 S39.376,168,25.376,168z M25.376,205.2c-5.168,0-9.376-4.752-9.376-10.6c0-5.848,4.208-10.6,9.376-10.6 c5.168,0,9.384,4.76,9.384,10.6C34.76,200.44,30.552,205.2,25.376,205.2z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M193.336,416.416c-4.208-1.408-8.728,0.864-10.12,5.056l-12.352,37.064c-1.096,3.272-4.144,5.464-7.592,5.464h-23.976 c-2.48,0-4.776-1.128-6.304-3.08c-1.528-1.952-2.056-4.456-1.456-6.864l11.024-44.12c1.072-4.28-1.536-8.624-5.824-9.696 c-4.264-1.08-8.624,1.52-9.704,5.824l-11.024,44.112c-1.808,7.224-0.216,14.736,4.36,20.592c4.584,5.864,11.48,9.232,18.928,9.232 h23.976c10.344,0,19.496-6.592,22.768-16.408l12.352-37.064C199.792,422.336,197.52,417.808,193.336,416.416z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M344.712,447.288L333.68,404.12c-1.096-4.272-5.408-6.872-9.736-5.768c-4.272,1.096-6.856,5.456-5.768,9.736 l11.024,43.128c0.736,2.96-0.232,6.832-2.432,9.648c-0.912,1.168-2.824,3.136-5.328,3.136h-23.976c-2.176,0-6.048-3.712-7.6-8.384 l-12.36-36.112c-1.44-4.176-5.976-6.384-10.168-4.976c-4.176,1.432-6.408,5.984-4.976,10.168l12.336,36.04 C277.88,470.312,286.72,480,297.464,480h23.976c6.792,0,13.336-3.392,17.944-9.296 C344.608,464.016,346.656,455.056,344.712,447.288z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M239.624,421.704c-7.688,0.36-15.632,0.392-23.28,0.064c-4.24-0.32-8.152,3.232-8.336,7.64 c-0.192,4.416,3.232,8.152,7.648,8.344c3.704,0.168,7.456,0.248,11.264,0.248c4.536,0,9.024-0.104,13.464-0.312 c4.416-0.208,7.824-3.952,7.616-8.368C247.784,424.896,243.832,421.328,239.624,421.704z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M273.416,205.824c-15.256-2.752-31.408-4.144-48-4.144c-19.696,0-38.336,1.696-55.48,5.04l-32.256-27.208 c-9.608-8.128-23.56-7.528-32.448,1.368l-8.776,8.776c-4.528,4.528-7.024,10.552-7.024,16.968c0,0.4,0.024,0.792,0.088,1.176 l5.472,36.736C88.464,249.92,75.656,262.072,67.6,280h-2.168C52.192,280,40,290.768,40,304v34.952 c0,7.56,4.336,14.768,10.4,19.264c0.288,0.208,0.936,0.408,1.248,0.576l38.632,21.32c4.616,6.36,10.112,12.344,16.168,17.816 c1.528,1.384,3.488,2.064,5.4,2.064c2.184,0,4.384-0.888,5.968-2.64c2.968-3.28,2.728-8.344-0.552-11.296 c-5.656-5.104-10.624-10.696-14.784-16.64c-0.696-1-1.616-1.824-2.672-2.416L59.6,345.104c-1.816-1.512-3.6-3.776-3.6-6.152V304 c0-4.416,5.016-8,9.432-8h7.528c3.344,0,6.336-2.08,7.504-5.216c8.416-22.664,27.48-36.064,27.656-36.176 c2.52-1.736,3.84-4.752,3.392-7.784l-6.064-40.688c0.12-1.952,0.936-3.776,2.328-5.168l8.784-8.776 c2.96-2.968,7.616-3.168,10.816-0.456l33.36,28.152c1.784,2.656,5.056,4.072,8.312,3.352c17.192-3.688,36.16-5.552,56.376-5.552 c15.64,0,30.832,1.304,45.168,3.888c4.32,0.8,8.504-2.112,9.288-6.464C280.664,210.768,277.768,206.608,273.416,205.824z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M432,280c-4.424,0-8,3.576-8,8v16c0,13.232-10.768,24-24,24h-7.32c0.208-2.704,0.424-5.392,0.424-8.16 c0-4.424-3.576-8-8-8s-8,3.576-8,8c0,26.224-10.456,48.456-31.056,66.08c-3.36,2.864-3.76,7.92-0.88,11.272 c1.584,1.856,3.824,2.808,6.08,2.808c1.84,0,3.696-0.632,5.192-1.92c17.456-14.92,28.84-33.344,33.792-54.08H400 c22.056,0,40-17.944,40-40v-16C440,283.576,436.424,280,432,280z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M249.432,232h-40c-4.416,0-8,3.576-8,8s3.584,8,8,8h40c4.424,0,8-3.576,8-8S253.848,232,249.432,232z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M355.816,178.872c-33.16,0-60.128,26.968-60.128,60.128c0,33.16,26.968,60.128,60.128,60.128 c33.16,0,60.136-26.968,60.136-60.128C415.952,205.84,388.976,178.872,355.816,178.872z M355.808,283.128 c-24.328,0-44.128-19.8-44.128-44.128s19.8-44.128,44.128-44.128c24.344,0,44.136,19.8,44.136,44.128 S380.144,283.128,355.808,283.128z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M380.032,217.168c-3.776-2.296-8.696-1.096-10.992,2.68l-14.968,24.632l-11.192-10.352c-3.248-3-8.312-2.816-11.312,0.44 c-3,3.24-2.808,8.304,0.44,11.312l18.368,17c1.496,1.376,3.44,2.128,5.44,2.128c0.368,0,0.744-0.024,1.112-0.08 c2.376-0.328,4.48-1.72,5.728-3.768l20.056-33C385.008,224.384,383.808,219.464,380.032,217.168z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M424,40c-4.424,0-8,3.576-8,8v19.2c0,4.424,3.576,8,8,8s8-3.576,8-8V48C432,43.576,428.424,40,424,40z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M424,116.8c-4.424,0-8,3.576-8,8V144c0,4.424,3.576,8,8,8s8-3.576,8-8v-19.2C432,120.376,428.424,116.8,424,116.8z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M472,88h-19.2c-4.424,0-8,3.576-8,8s3.576,8,8,8H472c4.424,0,8-3.576,8-8S476.424,88,472,88z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M395.2,88H376c-4.424,0-8,3.576-8,8s3.576,8,8,8h19.2c4.424,0,8-3.576,8-8S399.624,88,395.2,88z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M463.592,124.28l-13.568-13.568c-3.128-3.128-8.184-3.128-11.312,0c-3.128,3.128-3.128,8.184,0,11.312l13.568,13.568 c1.56,1.56,3.608,2.344,5.656,2.344c2.048,0,4.096-0.784,5.656-2.344C466.72,132.464,466.72,127.408,463.592,124.28z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M409.288,69.976L395.72,56.408c-3.128-3.128-8.184-3.128-11.312,0c-3.128,3.128-3.128,8.184,0,11.312l13.568,13.568 c1.56,1.56,3.608,2.344,5.656,2.344c2.048,0,4.096-0.784,5.656-2.344C412.416,78.168,412.416,73.104,409.288,69.976z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M409.288,110.712c-3.128-3.128-8.184-3.128-11.312,0l-13.568,13.568c-3.128,3.128-3.128,8.184,0,11.312 c1.56,1.56,3.608,2.344,5.656,2.344c2.048,0,4.096-0.784,5.656-2.344l13.568-13.568 C412.416,118.896,412.416,113.84,409.288,110.712z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M463.592,56.408c-3.128-3.128-8.184-3.128-11.312,0l-13.568,13.568c-3.128,3.128-3.128,8.184,0,11.312 c1.56,1.56,3.608,2.344,5.656,2.344c2.048,0,4.096-0.784,5.656-2.344l13.568-13.568C466.72,64.592,466.72,59.536,463.592,56.408z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <circle cx="132" cy="284" r="12"></circle>
                          </g>
                        </g>
                        <g>
                          <g>
                            <circle cx="200" cy="88" r="8"></circle>
                          </g>
                        </g>
                        <g>
                          <g>
                            <circle cx="288" cy="88" r="8"></circle>
                          </g>
                        </g>
                      </g>
                    </svg>
                    <span class="font-medium">{{ getPaymentProgress(quota) }}</span>
                  </div>
                  <!-- Fecha de pago -->
                  <div class="flex items-center text-sm text-gray-700 col-7 pt-2 md:pt-0 col-md-2 ">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">Fecha limite: {{ formatDate(quota.due_date) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="px-4 py-2 md:py-3 border-t cursor-pointer" :class="`bg-${getStatusInfo(quota.status).color}`"
              @click="goTo(quota)">
              <div class="flex justify-center items-center">
                <div class="flex items-center">
                  <q-icon :name="getStatusInfo(quota.status).icon" color="white" size="1.5rem" />
                  <span class="ml-1 text-sm font-medium text-white">
                    Total S/. {{
                      Number(quota.amount).toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <div v-html="appIcons.mensuality" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay cuotas registradas</h3>
          <p class="text-gray-600 text-center mb-6">Aún no se ha emitido ninguna cuota mensual.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped></style>

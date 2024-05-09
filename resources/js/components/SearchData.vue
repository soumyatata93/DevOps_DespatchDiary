<template>
      <b-row class="searchForm" style="margin-left:1px;">
           <FormKit type="group">
                 <b-col cols="3">
                       <FormKit
                             type="select"
                             label="Select Branch"
                             name="SelectedBranch"
                             v-model="inputData.selectedBranch"
                             @change="inputData.setCookiesForInputData"
                            
                       >
                       
                       <option id="searchDropdown" v-for="(branch) in inputData.branches" 
                       :key="branch['branch_id']" :value="branch['branch_id']">
                        {{branch['branch_location']}}
                             </option>     
                       </FormKit>
                 </b-col>
                 
                 <b-col cols="3">
                       <FormKit
                             type="date"
                             label="select date"
                             validation="required"
                             v-model="inputData.selectedDate"
                             @click="inputData.setCookiesForInputData"
                       />
                 </b-col>
                 <b-col cols="1" class="searchBtn">
                       <FormKit
                             type="button"
                             @click="submitData"
                             style="background-color:#0275ff;padding-right:25px;margin-left:10px;"
                       >
                       Search
                       </FormKit>
                 </b-col>
                 <b-col cols="3"><!--<b-button variant="outline-primary" @click="printTable">Print</b-button>--></b-col>
                 <b-col cols="1">
                  <b-button style="background-color:#0275ff;" @click="printDaySheet">Print</b-button>
                   
                  </b-col>
           </FormKit>
     </b-row>
            
</template>

<script setup>
import { search } from '../store/searchData.js'
import { input } from '../store/input.js'
import { VueCookieNext } from 'vue-cookie-next';
import { PrintDayTableData } from '../store/printDaySheet.js'
      const searchData = search();
      const inputData = input();

    
      
      const submitData = () => {

            VueCookieNext.setCookie('selected_branch', inputData.selectedBranch, { path: '/' });
            VueCookieNext.setCookie('selected_date', inputData.selectedDate, { path: '/' });
            inputData.selectedBranch=VueCookieNext.getCookie('selected_branch');
            inputData.selectedDate=VueCookieNext.getCookie('selected_date');
           
            searchData.SearchRequestedData(inputData.selectedBranch,inputData.selectedDate);
               
      }
      const props = defineProps({
        singleOrder: Object,
       
      })
      const printTable = PrintDayTableData();
      function printDaySheet(){
           printTable.printDayTable(searchData.printDayDeliverySheet,inputData.selectedDate);
      }
</script>

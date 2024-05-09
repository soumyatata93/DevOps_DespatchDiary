
import { defineStore } from 'pinia'
import routes from '../router/index.js';
import axios from "axios";
import { VueCookieNext } from 'vue-cookie-next';
import { input } from '../store/input.js'
import {useLoading} from 'vue-loading-overlay'



export const PrintDayTableData = defineStore('defineStore',{
      id:  'PrintTable',
      state: () => ({
            tableIdToPrint:'',
            htmlMarkup:'',
      }),

      actions:{
            printDayTable(selectedDayDeliverySheet,selectedDate){
                //console.log(selectedDayDeliverySheet);
                let groupedData = {};
                for (const [key, dayRecords] of Object.entries(selectedDayDeliverySheet)) {
                    for (const [runs, orderTable] of Object.entries(dayRecords)) {
                        for (let index in orderTable) {
                            if (
                                orderTable[index].order_no !== undefined &&
                                orderTable[index].ship_to_post_code !== undefined &&
                                orderTable[index].ship_to_name !== undefined &&
                                orderTable[index].shipment_type !== undefined &&
                                orderTable[index].van_number !== undefined
                            ) {
                                const vanNumber = orderTable[index].van_number;

                                // Create a group for the van if it doesn't exist
                                if (!groupedData[vanNumber]) {
                                    groupedData[vanNumber] = {
                                        van: vanNumber,
                                        name: orderTable[index].van_name, // Assuming van_name is available
                                        orders: [],
                                        comments: orderTable['comments'],
                                    };
                                }

                                // Add the order details to the corresponding van group
                                groupedData[vanNumber].orders.push({
                                    orderNo: orderTable[index].order_no,
                                    name: orderTable[index].ship_to_name,
                                    postCode: orderTable[index].ship_to_post_code,
                                    Type: orderTable[index].shipment_type,
                                });
                            }
                        }
                    }
                }

                // Convert the groupedData object to an array
                let array = Object.values(groupedData);
                let print = window.open('', '', 'height=950, width=1000');
                print.document.write('<html>');
                print.document.write('<body> <h1 style="text-align:center;">DAY DELIVERY SHEET FOR ALL VANS</h1> <br>');
                print.document.write('<div style="border-collapse: collapse; width:800px;border: 1px solid black">');
                
                print.document.write('<table style="border-collapse: collapse; width:800px;border: 1px solid black">');
                print.document.write('<thead><tr><th colspan="7" style="text-align:center;"><b>' + selectedDate + '</b></th></tr>');
                print.document.write('<tr>');
                print.document.write('<th style="border-collapse: collapse; border: 1px solid black; width:100px; scope="col">Order</th>');
                print.document.write('<th style="border-collapse: collapse; border: 1px solid black; width:100px; scope="col">Name</th>');
                print.document.write('<th style="border-collapse: collapse; border: 1px solid black; width:100px; scope="col">Post Code</th>');
                print.document.write('<th style="border-collapse: collapse; border: 1px solid black; width:100px; scope="col">Delivery Type</th>');
                print.document.write('<th style="border-collapse: collapse; border: 1px solid black; width:100px; scope="col">Progress</th>');
                print.document.write('<th style="border-collapse: collapse; border: 1px solid black; width:100px; scope="col">Comments</th>');
                print.document.write('<th style="border-collapse: collapse; border: 1px solid black; width:100px; scope="col">Sign</th>');
                print.document.write('</tr></thead>');
                print.document.write('<tbody">');  
                for (let i = 0; i < array.length; i++) {
                    print.document.write('<tr style="text-align:center;">');
                    print.document.write('<td colspan="7" style="text-align:center;height:30px;"><b>'+ array[i].van + '</b>&nbsp;&nbsp;  ' + array[i].comments + '</td>');
                    print.document.write('</tr>');
                    // Iterate over the orders array for the current van
                    for (let j = 0; j < array[i].orders.length; j++) {
                        print.document.write('<tr style="text-align:center">');
                        print.document.write('<td style="border-collapse: collapse; border: 1px solid black; width:100px;height:30px;">' + array[i].orders[j].orderNo + '</td>');
                        print.document.write('<td style="border-collapse: collapse; border: 1px solid black; width:100px">' + array[i].orders[j].name + '</td>');
                        print.document.write('<td style="border-collapse: collapse; border: 1px solid black; width:100px">' + array[i].orders[j].postCode + '</td>');
                        print.document.write('<td style="border-collapse: collapse; border: 1px solid black; width:100px">' + array[i].orders[j].Type + '</td>');
                        print.document.write('<td style="border-collapse: collapse; border: 1px solid black; width:100px"> </td>');
                        print.document.write('<td style="border-collapse: collapse; border: 1px solid black; width:100px"> </td>');
                        print.document.write('<td style="border-collapse: collapse; border: 1px solid black; width:100px"> </td>');
                        print.document.write('</tr>');
                    }
                }
                
                print.document.write('</tbody>');
                print.document.write('</table>');
                print.document.write('</div>');
                print.document.write('</body></html>');
                print.document.close();
                print.print();  
            }
      },
     
  
})

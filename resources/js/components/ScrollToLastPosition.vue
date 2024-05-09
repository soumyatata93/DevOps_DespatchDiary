<template>

{{ props.scrl }}
</template>

<script setup >
import { onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
      scrl: Number,
   
  });


  function logScroll(){
    // console.log(window.scrollY)
    let scrollPos = window.scrollY;
     

    localStorage.setItem("scroll", scrollPos)
  }

onMounted(() => {
  let scrollPos = localStorage.getItem("scroll");
  window.scrollTo(0,scrollPos);
  window.addEventListener('scroll', logScroll)
  // window.addEventListener('scroll', handleScroll());
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', logScroll)
})



// // let scrol = window.top.scrollY //getting current value of Y scroll

// window.addEventListener("scroll", console.log(window.top.scrollY))
//  console.log("printing window.top.scrollY");
 
// console.log(window.top.scrollY);

</script>

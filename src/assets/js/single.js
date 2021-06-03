
// Allowance for fixed header
const offsetHeader = 100

const vueApp = new Vue({
    el: '#vapp',
    delimiters: ["<%","%>"],
    data: { 
        loading: true,
        stepinfo: {},
        step: 0,
        furtheststep: 0,
        config: {},
        purchase: {},
        numbers: {},
        stage: 'numbers',
        isClassStandard: false,
        isClassFirst: false,
        passengersselected: false,
        destinationselected: '',
        joiningselected: '',
        classselected: '',
        supp: {
            valid: false,
        },
        form: {
            passengers: 0,
            children: 0,
        },
        childrenchanged: false,
        meals: {},
        mealquantities: {},
        classmeals: true,
        comment: '',
    },
    mounted: function() {
        const testrange = this.getNumbers(1,16)

        const configJSON = document.getElementById('jsenv').innerHTML
        this.config = JSON.parse(configJSON)

        // Purchase
        this.getMeals()
        const v = this
        axios.get(this.config.www + '/index.php/api/getpurchase/' + this.config.serviceid)
        .then(response => {
            const purchase = response.data
            v.purchase = purchase

            // Populate form
            //v.purchase = purchase
            v.form.passengers = purchase.adults + purchase.children
            v.form.children = purchase.children
            v.destinationselected = purchase.destination
            v.joiningselected = purchase.joining
            v.classselected = purchase.class
            v.comment = purchase.comment
            if (v.form.passengers) {
                v.passengersselected = true
            }

            // next one
            return axios.get(this.config.www + '/index.php/api/getbookingnumbers')
        })
        .then (response => {
            const numbers = response.data
            v.numbers = numbers
            return axios.get(this.config.www + '/index.php/api/getclasssupplemental')
        })
        .then(response => {
            v.supp = response.data
            return axios.get(this.config.www + '/index.php/api/getsteps')
        })
        .then(response => {
            v.stepinfo = response.data
            v.step = v.stepinfo.first
            v.furtheststep = v.step
            return axios.get(this.config.www + '/index.php/api/getmeals')
        })
        .then(response => {
            v.meals = response.data
            v.loading = false;
        })
        .catch(error => {
            iziToast.error({
                'title': 'Error',
                'message': 'Link to server has failed - ' + error.message,
            })
        })
    },

    methods: {

        // Process class options
        classClick(c, name) {

            // Has previously selected been changed?
            let reset = 0
            if (this.classselected && (this.classselected != c))  {
                this.form.passengers = 0
                this.form.children = 0
                this.passengersselected = false
                this.meals.forEach(meal => {
                    meal.purchase = 0;
                })
                reset = 1
                const classModal = new bootstrap.Modal(document.getElementById('classModal'))
                classModal.show()
            }
            this.classselected = c
            const v = this
            axios.get(this.config.www + '/index.php/api/setclass/' + c + '/' + reset)
            .then(response => {
                iziToast.success({
                    title: 'Travel class selected, ' + name
                })
                // changing class may take things out of limit
            })
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        },

        // Select destination
        destinationClick: function(crs, name) {
            this.destinationselected = crs
            axios.get(this.config.www + '/index.php/api/setdestination/' + crs)
            .then(response => {
                iziToast.success({
                    title: 'Destination selected ' + name
                })
            })
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        },

        // Select joining station
        joiningClick: function(crs, name) {
            this.joiningselected = crs
            axios.get(this.config.www + '/index.php/api/setjoining/' + crs)
            .then(response => {
                iziToast.success({
                    title: 'Selected, joining at ' + name
                })
            }) 
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        },

        // Range thing for (form) selects
        getNumbers:function(start,stop){
            return new Array(stop-start+1).fill(start).map((n,i)=>n+i);
        },

        // Passengers value changed
        passengersChange: function() {
            this.numbers.maxchildren = this.form.passengers - 1
            if (this.form.children > (this.form.passengers -1)) {
                this.form.children = this.form.passengers -1
                this.childrenchanged = true
                const passengerModal = new bootstrap.Modal(document.getElementById('passengerModal'))
                passengerModal.show()
            }
            const adults = this.form.passengers - this.form.children
            this.passengersselected = true
            const v = this
            axios.get(this.config.www + '/index.php/api/setpassengers/' + adults + '/' + this.form.children)
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        },

        // Children value change
        childrenChange: function() {
            const adults = this.form.passengers - this.form.children
            this.passengersselected = true
            axios.get(this.config.www + '/index.php/api/setpassengers/' + adults + '/' + this.form.children)
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        },

        mealChange: function(i) {
            axios.get(this.config.www + '/index.php/api/setmeal/' + this.meals[i].letter + '/' + this.meals[i].purchase)
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        },

        // Click steps breadcrumb
        stepChange: function(step) {
            if ((step in this.stepinfo.steps) && (step <= this.furtheststep)) {
                this.step = step
            }
        },

        submitPage: function() {
            window.location.href = this.config.www + '/index.php/booking/single/' + this.config.serviceid + '/submit'
        },

        pageNext: function(page) {
            // MAGIC NUMBER: change this if pages change
            const maxpage = 6
            while (!(page in this.stepinfo.steps) && (page < maxpage)) {
                page++
            }
            this.step = page
            if (this.furtheststep > page) {
                this.furtheststep = page
            }

            if (page == maxpage) {
                this.submitPage()
            }
        },

        pageBack: function(page) {
            while (!(page in this.stepinfo.steps) && (page > 0)) {
                page--
            }
            if (page == 0) {
                this.step = this.stepinfo.first
            }
            this.step = page
        },

        getMeals: function() {
            const v = this
            axios.get(this.config.www + '/index.php/api/getmeals')
            .then(response => {
                v.meals = response.data
                v.meals.forEach(meal => {
                    v.mealquantities[meal.letter] = {
                        quantity: meal.purchase,
                        name: meal.name
                    }
                })
                return axios.get(this.config.www + '/index.php/api/getclassmeals')
            })
            .then(response => {
                v.classmeals = response.data
            })
            .catch(error => {
                iziToast.error({
                    'title': 'Error',
                    'message': 'Link to server has failed - ' + error.message,
                })
            })
        }
    },

    watch: {
        step: {
            immediate: true,
            handler(newStep, oldStep) {
                const v = this
                if (newStep == 3) {
                    axios.get(this.config.www + '/index.php/api/getclasssupplemental')
                    .then(response => {
                        v.supp = response.data
                    })
                    .catch(error => {
                        iziToast.error({
                            'title': 'Error',
                            'message': 'Link to server has failed - ' + error.message,
                        })
                    })
                }
                if (newStep == 4) {
                    axios.get(this.config.www + '/index.php/api/getbookingnumbers')
                    .then(response => {
                        v.numbers = response.data
                    })
                    .catch(error => {
                        iziToast.error({
                            'title': 'Error',
                            'message': 'Link to server has failed - ' + error.message,
                        })
                    })
                }
                if (newStep == 5) {
                    this.getMeals()
                }
            }
        }
    }

})
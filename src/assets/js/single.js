

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
    },
    mounted: function() {
        const testrange = this.getNumbers(1,16)
        window.console.log(testrange)

        const configJSON = document.getElementById('jsenv').innerHTML
        this.config = JSON.parse(configJSON)

        // Purchase
        const v = this
        axios.get(this.config.www + '/index.php/api/getpurchase/' + this.config.serviceid)
        .then(response => {
            const purchase = response.data

            // Populate form
            //v.form.adults = purchase.adults ? purchase.adults : 1
            v.form.passengers = purchase.adults + purchase.children
            v.form.children = purchase.children
            v.destinationselected = purchase.destination
            v.joiningselected = purchase.joining
            v.classselected = purchase.class
            if (v.form.passengers) {
                v.passengersselected = true
            }

            window.console.log('PURCHASE ACQUIRED')
            window.console.log(purchase)
            window.console.log(v.form)

            // next one
            return axios.get(this.config.www + '/index.php/api/getbookingnumbers')
        })
        .then (response => {
            const numbers = response.data
            v.numbers = numbers
            window.console.log('NUMBERS ACQUIRED')
            window.console.log(numbers)
            return axios.get(this.config.www + '/index.php/api/getclasssupplemental')
        })
        .then(response => {
            v.supp = response.data
            window.console.log('GOT CLASS SUPP')
            window.console.log(v.supp)
            return axios.get(this.config.www + '/index.php/api/getsteps')
        })
        .then(response => {
            v.stepinfo = response.data
            v.step = v.stepinfo.first
            v.furtheststep = v.step
            window.console.log('GOT STEPS')
            window.console.log(v.stepinfo)
            v.loading = false
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
            window.console.log('CLICKED ' + c)
            this.classselected = c
            const v = this
            axios.get(this.config.www + '/index.php/api/setclass/' + c)
            .then(() => {
                return axios.get(this.config.www + '/index.php/api/getbookingnumbers')
            })
            .then(response => {
                window.console.log('GOT CLICK NUMBERS')
                window.console.log(response.data)
                v.numbers = response.data
                v.step = 4
                if (v.furtheststep > 4) {
                    v.furtheststep = 4
                }
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
            const v = this
            axios.get(this.config.www + '/index.php/api/setdestination/' + crs)
            .then(() => {
                return axios.get(this.config.www + '/index.php/api/getclasssupplemental')
            })
            .then(response => {
                v.supp = response.data
                v.step = 2
                if (v.furtheststep < 2) {
                    v.furtheststep = 2
                }
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
            const v = this
            axios.get(this.config.www + '/index.php/api/setjoining/' + crs)
            .then(() => {
                return axios.get(this.config.www + '/index.php/api/getclasssupplemental')
            })
            .then(response => {
                v.supp = response.data
                v.step = 3
                if (v.furtheststep < 3) {
                    v.furtheststep = 3
                }
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

        // Update fares info in class select screens
        updateFares: function() {
            window.console.log('GOT HERE')
            if (this.destinationselected && this.joiningselected) {
                window.console.log('D = ' + this.destinationselected + ' J = ' + this.joiningselected)
                axios.get(this.config.www + '/index.php/api/getclasssupplemental')
                .then(response => {
                    const supp = response.data
                    window.console.log('GOT CLASS SUPP')
                    window.console.log(supp)
                })
                .catch(error => {
                    iziToast.error({
                        'title': 'Error',
                        'message': 'Link to server has failed - ' + error.message,
                    })
                })
            }
        },

        // Passengers value changed
        passengersChange: function() {
            window.console.log('PASSENGER CHANGE ' + this.form.passengers)
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
            window.console.log('CHILDREN CHANGE ' + this.form.children)
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

        // next button on numbers
        numbersNext: function() {
            window.console.log('NUMBERS NEXT')
        },

        // Click steps breadcrumb
        stepChange: function(step) {
            window.console.log('STEP CHANGE ' + step)
            if ((step in this.stepinfo.steps) && (step <= this.furtheststep)) {
                this.step = step
            }
        }
    }


})
//const { default: axios } = require("axios")

const vueApp = new Vue({
    el: '#vapp',
    delimiters: ["<%","%>"],
    data: { 
        loading: true,
        config: {},
        purchase: {},
        numbers: {},
        stage: 'numbers',
        isClassStandard: false,
        isClassFirst: false,
        passengersselected: false,
        form: {
            passengers: 0,
            children: 0,
        },
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
        classClick(c) {
            window.console.log('CLICKED ' + c)
            let newclass = ''
            let message = ''
            if (c == 'first') {
                this.isClassFirst = true
                this.isClassStandard = false
                newclass = 'F'
                message = 'First'
            } else {
                this.isClassFirst = false
                this.isClassStandard = true
                newclass = 'S'
                message = 'Standard'
            }
            const v = this
            axios.get(this.config.www + '/index.php/api/setclass/' + newclass)
            .then(response => {
                iziToast.success({
                    'title': 'Saved',
                    'message': 'Travel class has been saved - ' + message
                })
                return axios.get(this.config.www + '/index.php/api/getbookingnumbers')
            })
            .then(response => {
                window.console.log('GOT CLICK NUMBERS')
                window.console.log(response.data)
                v.numbers = response.data
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
            window.console.log('PASSENGER CHANGE ' + this.form.passengers)
            this.numbers.maxchildren = this.numbers.maxparty - this.form.passengers
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
        }
    }

})
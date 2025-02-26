import React from 'react'
import _ from 'lodash'
import moment from 'moment'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import LocaleProvider from 'antd/lib/locale-provider'
import Switch from 'antd/lib/switch'
import Slider from 'antd/lib/slider'
import Form from 'antd/lib/form'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'
import { Formik } from 'formik'
import Select from 'react-select'

import TagsSelect from '../../components/TagsSelect'
import Avatar from './Avatar'
import {
  closeFiltersModal,
  showFinishedTasks,
  hideFinishedTasks,
  showCancelledTasks,
  hideCancelledTasks,
  setFilterValue } from '../redux/actions'
import { selectBookedUsernames } from '../redux/selectors'

const locale = $('html').attr('lang')
const antdLocale = locale === 'fr' ? fr_FR : en_GB

function isHidden(hiddenCouriers, username) {
  return !!_.find(hiddenCouriers, u => u === username)
}

const timeSteps = {
  0: '00:00',
  4: '04:00',
  8: '08:00',
  12: '12:00',
  16: '16:00',
  20: '20:00',
  24: '23:59',
}

class FiltersModalContent extends React.Component {

  _onSubmit(values, { setSubmitting }) {

    this.props.setFilterValue('showFinishedTasks', values.showFinishedTasks)
    this.props.setFilterValue('showCancelledTasks', values.showCancelledTasks)
    this.props.setFilterValue('tags', values.tags)
    this.props.setFilterValue('hiddenCouriers', values.hiddenCouriers)
    this.props.setFilterValue('timeRange', values.timeRange)

    this.props.closeFiltersModal()
  }

  render() {

    let initialValues = {
      showFinishedTasks: this.props.showFinishedTasks,
      showCancelledTasks: this.props.showCancelledTasks,
      tags: this.props.selectedTags,
      hiddenCouriers: this.props.hiddenCouriers,
      timeRange: this.props.timeRange,
    }

    return (
      <Formik
        initialValues={ initialValues }
        onSubmit={ this._onSubmit.bind(this) }
        validateOnBlur={ false }
        validateOnChange={ false }
      >
        {({
          values,
          errors,
          touched,
          handleChange,
          handleBlur,
          handleSubmit,
          isSubmitting,
          isValidating,
          setFieldValue,
          setFieldTouched,
        }) => (
          <LocaleProvider locale={ antdLocale }>
            <form onSubmit={ handleSubmit } autoComplete="off" className="form-horizontal">
              <ul className="nav nav-tabs" role="tablist">
                <li role="presentation" className="active">
                  <a href="#filters_general" aria-controls="filters_general" role="tab" data-toggle="tab">
                    { this.props.t('ADMIN_DASHBOARD_FILTERS_TAB_GENERAL') }
                  </a>
                </li>
                <li role="presentation">
                  <a href="#filters_tags" aria-controls="filters_tags" role="tab" data-toggle="tab">
                    { this.props.t('ADMIN_DASHBOARD_FILTERS_TAB_TAGS') }
                  </a>
                </li>
                <li role="presentation">
                  <a href="#filters_couriers" aria-controls="filters_couriers" role="tab" data-toggle="tab">
                    { this.props.t('ADMIN_DASHBOARD_FILTERS_TAB_COURIERS') }
                  </a>
                </li>
                <li role="presentation">
                  <a href="#filters_timerange" aria-controls="filters_timerange" role="tab" data-toggle="tab">
                    { this.props.t('ADMIN_DASHBOARD_FILTERS_TAB_TIMERANGE') }
                  </a>
                </li>
              </ul>
              <div className="tab-content">
                <div role="tabpanel" className="tab-pane active" id="filters_general">
                  <div className="dashboard__modal-filters__tabpane">
                    <Form.Item label={ this.props.t('ADMIN_DASHBOARD_FILTERS_COMPLETED_TASKS') }
                      labelCol={{ span: 12 }} wrapperCol={{ span: 12 }} colon={ false }>
                      <Switch
                        checkedChildren={ this.props.t('ADMIN_DASHBOARD_FILTERS_SHOW') }
                        unCheckedChildren={ this.props.t('ADMIN_DASHBOARD_FILTERS_HIDE') }
                        defaultChecked={ values.showFinishedTasks }
                        onChange={ (checked) => setFieldValue('showFinishedTasks', checked) } />
                    </Form.Item>
                    <Form.Item label={ this.props.t('ADMIN_DASHBOARD_FILTERS_CANCELLED_TASKS') }
                      labelCol={{ span: 12 }} wrapperCol={{ span: 12 }} colon={ false }>
                      <Switch
                        checkedChildren={ this.props.t('ADMIN_DASHBOARD_FILTERS_SHOW') }
                        unCheckedChildren={ this.props.t('ADMIN_DASHBOARD_FILTERS_HIDE') }
                        defaultChecked={ values.showCancelledTasks }
                        onChange={ (checked) => setFieldValue('showCancelledTasks', checked) } />
                    </Form.Item>
                  </div>
                </div>
                <div role="tabpanel" className="tab-pane" id="filters_tags">
                  <div className="dashboard__modal-filters__tabpane">
                    <TagsSelect tags={ this.props.tags }
                      defaultValue={ this.props.selectedTags }
                      onChange={ tags => setFieldValue('tags', _.map(tags, tag => tag.slug)) } />
                  </div>
                </div>
                <div role="tabpanel" className="tab-pane" id="filters_couriers">
                  <div className="dashboard__modal-filters__tabpane">
                    { this.props.couriers.map(username => (
                      <div className="dashboard__modal-filters__courier" key={ username }>
                        <span>
                          <Avatar username={ username } /> <span>{ username }</span>
                        </span>
                        <div>
                          <Switch
                            checkedChildren={ this.props.t('ADMIN_DASHBOARD_FILTERS_SHOW') }
                            unCheckedChildren={ this.props.t('ADMIN_DASHBOARD_FILTERS_HIDE') }
                            defaultChecked={ !isHidden(values.hiddenCouriers, username) }
                            onChange={ checked => {
                              if (checked) {
                                setFieldValue('hiddenCouriers', _.filter(values.hiddenCouriers, u => u !== username))
                              } else {
                                setFieldValue('hiddenCouriers', values.hiddenCouriers.concat([ username ]))
                              }
                            }} />
                        </div>
                      </div>
                    )) }
                  </div>
                </div>
                <div role="tabpanel" className="tab-pane" id="filters_timerange">
                  <div className="dashboard__modal-filters__tabpane">
                    <Slider range
                      marks={ timeSteps }
                      defaultValue={ values.timeRange }
                      max={ 24 }
                      step={ null }
                      onAfterChange={ value => setFieldValue('timeRange', value) } />
                  </div>
                </div>
              </div>
              <button type="submit" className="btn btn-block btn-primary">
                { this.props.t('ADMIN_DASHBOARD_FILTERS_APPLY') }
              </button>
            </form>
          </LocaleProvider>
        )}
      </Formik>
    )
  }
}

function mapStateToProps(state) {

  return {
    tags: state.tags,
    showFinishedTasks: state.filters.showFinishedTasks,
    showCancelledTasks: state.filters.showCancelledTasks,
    selectedTags: state.filters.tags,
    couriers: selectBookedUsernames(state),
    hiddenCouriers: state.filters.hiddenCouriers,
    timeRange: state.filters.timeRange,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeFiltersModal: _ => dispatch(closeFiltersModal()),
    setFilterValue: (key, value) => dispatch(setFilterValue(key, value)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(FiltersModalContent))
